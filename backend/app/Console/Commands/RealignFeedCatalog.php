<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorMarketDetector;
use Illuminate\Console\Command;

class RealignFeedCatalog extends Command
{
    protected $signature = 'personal:realign-feed-catalog
        {--limit=100 : Maximum creators and posts to process in this pass}
        {--creator= : Restrict the pass to one creator handle}
        {--markets-only : Skip content safety and only realign markets and verticals}
        {--dry-run : Report decisions without writing data}';

    protected $description = 'Realign feed safety, creator markets and canonical verticals';

    public function handle(
        ContentSafetyPolicy $safety,
        CreatorMarketDetector $markets,
        CanonicalCreatorVerticals $verticals,
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $creatorResult = $this->realignCreators($limit, $safety, $markets, $verticals, $dryRun);
        $postResult = $this->option('markets-only')
            ? ['checked' => 0, 'allowed' => 0, 'blocked' => 0, 'pending' => 0]
            : $this->realignPosts($limit, $safety, $dryRun);
        $mode = $dryRun ? 'Dry run' : 'Realignment';

        $this->info(sprintf(
            '%s: %d creators checked, %d creators allowed, %d creators blocked, %d creators pending; %d markets resolved, %d markets unresolved, %d verticals aligned; %d posts checked, %d posts allowed, %d posts blocked, %d posts pending.',
            $mode,
            $creatorResult['checked'],
            $creatorResult['allowed'],
            $creatorResult['blocked'],
            $creatorResult['pending'],
            $creatorResult['markets'],
            $creatorResult['markets_unresolved'],
            $creatorResult['verticals'],
            $postResult['checked'],
            $postResult['allowed'],
            $postResult['blocked'],
            $postResult['pending'],
        ));

        return self::SUCCESS;
    }

    /** @return array{checked: int, allowed: int, blocked: int, pending: int, markets: int, markets_unresolved: int, verticals: int} */
    private function realignCreators(
        int $limit,
        ContentSafetyPolicy $safety,
        CreatorMarketDetector $markets,
        CanonicalCreatorVerticals $verticals,
        bool $dryRun,
    ): array {
        $result = ['checked' => 0, 'allowed' => 0, 'blocked' => 0, 'pending' => 0, 'markets' => 0, 'markets_unresolved' => 0, 'verticals' => 0];

        if (! $this->option('markets-only')) {
            Creator::query()
                ->where('safety_status', '!=', ContentSafetyDecision::BLOCKED)
                ->where('safety_policy_version', '<', ContentSafetyPolicy::VERSION)
                ->when($this->option('creator'), function ($query, string $username): void {
                    $query->where('username', $username);
                })
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->each(function (Creator $creator) use ($safety, $dryRun, &$result): void {
                    $decision = $safety->storedCreator($creator);
                    $result['checked']++;
                    $result[$decision->status]++;

                    if ($dryRun) {
                        return;
                    }

                    $creator->forceFill([
                        'safety_status' => $decision->status,
                        'safety_reasons' => $decision->reasons,
                        'safety_checked_at' => now(),
                        'safety_policy_version' => $decision->status === ContentSafetyDecision::PENDING
                            ? $creator->safety_policy_version
                            : ContentSafetyPolicy::VERSION,
                    ])->save();
                });
        }

        Creator::query()
            ->where(function ($query): void {
                $query->whereNull('market')->orWhereNull('primary_vertical');
            })
            ->when($this->option('creator'), function ($query, string $username): void {
                $query->where('username', $username);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Creator $creator) use ($markets, $verticals, $dryRun, &$result): void {
                $detectedMarket = $markets->detect($this->creatorSignals($creator));
                $detectedVertical = $verticals->fromSignals([
                    $creator->niche,
                    ...($creator->niche_topics ?? []),
                    $creator->bio,
                ]);
                $attributes = [];

                if ($detectedMarket['market'] !== null && $creator->market !== $detectedMarket['market']) {
                    $attributes['market'] = $detectedMarket['market'];
                }

                if ($creator->primary_language !== $detectedMarket['language']) {
                    $attributes['primary_language'] = $detectedMarket['language'];
                }

                if ($creator->primary_vertical !== $detectedVertical) {
                    $attributes['primary_vertical'] = $detectedVertical;
                }

                if ($detectedMarket['market'] !== null && $creator->market !== $detectedMarket['market']) {
                    $result['markets']++;
                } elseif ($creator->market === null) {
                    $result['markets_unresolved']++;
                }

                if ($detectedVertical !== $creator->primary_vertical) {
                    $result['verticals']++;
                }

                if (! $dryRun && $attributes !== []) {
                    $creator->forceFill($attributes)->save();
                }
            });

        return $result;
    }

    /** @return array{checked: int, allowed: int, blocked: int, pending: int} */
    private function realignPosts(int $limit, ContentSafetyPolicy $safety, bool $dryRun): array
    {
        $result = ['checked' => 0, 'allowed' => 0, 'blocked' => 0, 'pending' => 0];

        ContentPost::query()
            ->with('creator')
            ->where('safety_status', '!=', ContentSafetyDecision::BLOCKED)
            ->where('safety_policy_version', '<', ContentSafetyPolicy::VERSION)
            ->when($this->option('creator'), function ($query, string $username): void {
                $query->whereHas('creator', fn ($creator) => $creator->where('username', $username));
            })
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->each(function (ContentPost $post) use ($safety, $dryRun, &$result): void {
                $decision = $safety->storedPost($post);
                $result['checked']++;
                $result[$decision->status]++;

                if ($dryRun) {
                    return;
                }

                $attributes = [
                    'safety_status' => $decision->status,
                    'safety_reasons' => $decision->reasons,
                    'safety_checked_at' => now(),
                    'safety_policy_version' => $decision->status === ContentSafetyDecision::PENDING
                        ? $post->safety_policy_version
                        : ContentSafetyPolicy::VERSION,
                ];

                if ($decision->status !== ContentSafetyDecision::ALLOWED) {
                    $attributes += [
                        'measured_at' => null,
                        'outlier_score' => 0,
                        'performance_ratio' => 0,
                        'engagement_rate' => 0,
                    ];
                }

                $post->forceFill($attributes)->save();
            });

        return $result;
    }

    private function creatorSignals(Creator $creator): string
    {
        return implode("\n", array_filter([
            $creator->display_name,
            $creator->bio,
            $creator->niche,
            ...($creator->niche_topics ?? []),
            $creator->posts()->latest('published_at')->limit(20)->pluck('caption')->filter()->implode("\n"),
        ]));
    }
}
