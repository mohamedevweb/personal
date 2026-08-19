<?php

namespace App\Jobs;

use App\Exceptions\ContentDiscoveryException;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\ProfileDiscoveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns seeded creators into ranked accounts: re-scrape each account's profile
 * for its real follower count and recent posts, then compute an engagement rate
 * that is comparable across every account. Hashtag discovery only ever seeds the
 * creator list; this job is what makes "the 30 hottest cooking accounts" a query.
 */
class MeasureAccountEngagement implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public readonly string $niche) {}

    public function handle(ProfileDiscoveryService $profiles): void
    {
        $due = $this->dueCreators();

        if ($due->isEmpty()) {
            return;
        }

        try {
            $scraped = $profiles->profiles(
                $due->pluck('username')->all(),
                (int) config('services.discovery.profile_posts'),
            );
        } catch (ContentDiscoveryException $exception) {
            // Measurement is best-effort: log and leave existing rankings intact
            // rather than failing the queue.
            Log::warning('Account engagement measurement skipped.', ['niche' => $this->niche, 'exception' => $exception]);

            return;
        }

        foreach ($scraped as $profile) {
            $this->measure($profile);
        }
    }

    /**
     * Creators in this niche whose measurement cooldown has lapsed. New creators
     * (never measured) are always due.
     *
     * @return Collection<int, Creator>
     */
    private function dueCreators(): Collection
    {
        return Creator::query()
            ->where('niche', $this->niche)
            ->where(function ($query): void {
                $query->whereNull('last_measured_at')
                    ->orWhere('last_measured_at', '<', now()->subDays((int) config('services.discovery.measure_cooldown_days')));
            })
            ->limit((int) config('services.discovery.measure_batch'))
            ->get();
    }

    private function measure(DiscoveredProfile $profile): void
    {
        $creator = Creator::query()->updateOrCreate(
            ['username' => $profile->username],
            [
                'display_name' => $profile->displayName ?: $profile->username,
                'avatar_url' => $profile->avatarUrl,
                'niche' => $this->niche,
                'followers' => $profile->followers,
                'average_views' => (int) $profile->posts->map(fn (DiscoveredPost $p): int => $p->views)->avg(),
                'average_likes' => (int) $profile->posts->map(fn (DiscoveredPost $p): int => $p->likes)->avg(),
                'avg_engagement_rate' => $profile->engagementRate(),
                'last_measured_at' => now(),
            ],
        );

        // performance_ratio is now measured against the account's own median
        // engagement, so it means "this post outperforms this creator" — valid
        // across the whole feed, unlike the per-batch median it replaces.
        $median = max(1, (int) $profile->posts->map(fn (DiscoveredPost $p): int => $p->engagement())->median());

        foreach ($profile->posts as $post) {
            $this->storePost($creator->id, $post, $median);
        }
    }

    private function storePost(int $creatorId, DiscoveredPost $post, int $median): void
    {
        ContentPost::query()->updateOrCreate(
            ['source_url' => $post->sourceUrl],
            [
                'creator_id' => $creatorId,
                'platform' => 'instagram',
                'format' => $post->format,
                'hook' => $this->hook($post),
                'caption' => $post->caption,
                'thumbnail_url' => $post->thumbnailUrl,
                'views' => $post->views,
                'likes' => $post->likes,
                'comments' => $post->comments,
                'published_at' => $post->publishedAt,
                'performance_ratio' => round($post->engagement() / $median, 2),
                'tags' => $post->hashtags,
                'why_it_works' => 'Outperforming its niche with '.number_format($post->likes).' likes and '
                    .number_format($post->comments).' comments.',
                'hook_analysis' => '',
                'structure_analysis' => '',
            ],
        );
    }

    private function hook(DiscoveredPost $post): string
    {
        $firstLine = trim((string) Str::of($post->caption)->before("\n"));

        return Str::limit($firstLine !== '' ? $firstLine : "New {$this->niche} post", 120);
    }
}
