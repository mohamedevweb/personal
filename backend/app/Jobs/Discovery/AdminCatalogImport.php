<?php

namespace App\Jobs\Discovery;

use App\Exceptions\ContentDiscoveryException;
use App\Jobs\Content\AnalyzeCarouselContentPost;
use App\Jobs\Content\AnalyzeContentPost;
use App\Jobs\Content\TranscribeContentPost;
use App\Models\AdminCatalogImport as AdminCatalogImportRecord;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorMarketDetector;
use App\Services\Discovery\CreatorNicheCatalog;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\OutlierScore;
use App\Services\Discovery\PostMetricsLifecycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminCatalogImport implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public readonly int $importId)
    {
        $this->onQueue('discovery');
    }

    public function handle(
        InstagramDataProvider $provider,
        CreatorNicheService $niches,
        CreatorNicheCatalog $catalog,
        OutlierScore $performance,
        ContentSafetyPolicy $safety,
        PostMetricsLifecycle $lifecycle,
        CreatorMarketDetector $markets,
    ): void {
        $import = AdminCatalogImportRecord::query()->findOrFail($this->importId);
        $import->forceFill([
            'status' => 'running',
            'error' => null,
            'started_at' => now(),
            'completed_at' => null,
        ])->save();

        try {
            $measurement = new MeasureAccountEngagement(
                usernames: [$import->creator_username ?: ''],
                marketHints: [$this->key($import->creator_username) => $import->country_code],
                force: true,
                postsLimit: (int) config('services.discovery.profile_posts'),
                verticalHints: [$this->key($import->creator_username) => $import->vertical],
            );

            $creator = $this->measureCreatorIfNeeded(
                $import,
                $measurement,
                $provider,
                $niches,
                $catalog,
                $performance,
                $safety,
                $lifecycle,
                $markets,
            );

            if ($import->type === 'post') {
                $post = $provider->getPost($import->url, $creator->username);

                if (! $post) {
                    throw new ContentDiscoveryException('Personal could not find this Instagram post. Check the URL and try again.');
                }

                if (mb_strtolower(ltrim($post->username, '@')) !== mb_strtolower(ltrim($creator->username, '@'))) {
                    throw new ContentDiscoveryException('This post does not belong to the selected creator.');
                }

                $content = $measurement->importPost($creator, $post, $safety, $lifecycle, $performance);
                $this->queueAnalysis($content->id);
                $import->content_post_id = $content->id;
            }

            $import->forceFill([
                'creator_id' => $creator->id,
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $message = $exception instanceof ContentDiscoveryException
                ? $exception->getMessage()
                : 'Personal could not import this Instagram item right now. Please try again.';

            $import->forceFill([
                'status' => 'failed',
                'error' => $message,
                'completed_at' => now(),
            ])->save();

            Log::error('Admin catalog import failed.', [
                'admin_catalog_import_id' => $import->id,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $import = AdminCatalogImportRecord::query()->find($this->importId);

        if (! $import || $import->status === 'completed') {
            return;
        }

        $import->forceFill([
            'status' => 'failed',
            'error' => $import->error ?: 'Personal could not complete this Instagram import. Please try again.',
            'completed_at' => now(),
        ])->save();
    }

    private function measureCreatorIfNeeded(
        AdminCatalogImportRecord $import,
        MeasureAccountEngagement $measurement,
        InstagramDataProvider $provider,
        CreatorNicheService $niches,
        CreatorNicheCatalog $catalog,
        OutlierScore $performance,
        ContentSafetyPolicy $safety,
        PostMetricsLifecycle $lifecycle,
        CreatorMarketDetector $markets,
    ): Creator {
        $creator = Creator::query()->find($import->creator_id);

        if ($import->type === 'creator' || ! $creator?->last_measured_at || ! $creator->performance_baselines) {
            $creator = $measurement->measureUsername(
                (string) $import->creator_username,
                $provider,
                $niches,
                $catalog,
                $performance,
                $safety,
                lifecycle: $lifecycle,
                markets: $markets,
            );
        }

        if (! $creator || ! $creator->exists || $creator->safety_status !== 'allowed') {
            throw new ContentDiscoveryException('Personal could not validate this Instagram creator. Check that the account is public and try again.');
        }

        $creator->forceFill([
            'primary_vertical' => $import->vertical,
            'market' => $import->country_code,
            'primary_language' => $import->country_code === 'FR' ? 'fr' : 'en',
            'curation_status' => 'approved',
            'is_catalog_seed' => true,
            'metadata' => array_replace_recursive($creator->metadata ?? [], [
                'catalog_import' => [
                    'vertical_override' => $import->vertical,
                    'market_override' => $import->country_code,
                ],
            ]),
        ])->save();

        if (filled($creator->niche) && is_array($creator->niche_topics)) {
            $catalog->sync($creator, $creator->niche, $creator->niche_topics, 'catalog');
        }

        return $creator->fresh() ?? $creator;
    }

    private function queueAnalysis(int $contentPostId): void
    {
        $analysis = new AnalyzeContentPost($contentPostId, app()->getLocale());
        $post = ContentPost::query()->find($contentPostId);

        if (! $post) {
            return;
        }

        $media = match (mb_strtolower((string) $post->format)) {
            'reel' => [new TranscribeContentPost($contentPostId)],
            'carousel' => [new AnalyzeCarouselContentPost($contentPostId)],
            default => [],
        };

        Bus::chain([...$media, $analysis])->dispatch();
    }

    private function key(?string $username): string
    {
        return mb_strtolower(ltrim((string) $username, '@'));
    }
}
