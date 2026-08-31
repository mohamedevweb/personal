<?php

namespace App\Services\Discovery;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use Illuminate\Support\Facades\DB;

class CreatorCatalogImporter
{
    public function __construct(
        private readonly CreatorNicheCatalog $niches,
        private readonly CreatorMarketDetector $markets,
        private readonly CreatorCatalogEligibility $eligibility,
    ) {}

    /** @param list<array<string, mixed>> $entries @return array{imported: int, skipped: list<string>, jobs: int} */
    public function import(array $entries, InstagramDataProvider $provider): array
    {
        $imported = [];
        $skipped = [];

        foreach ($entries as $entry) {
            if (($entry['status'] ?? null) !== 'approved') {
                continue;
            }

            $profile = $provider->getProfile((string) $entry['handle']);

            if (! $profile) {
                $skipped[] = (string) $entry['handle'];

                continue;
            }

            $creator = DB::transaction(function () use ($entry, $profile): Creator {
                $creator = Creator::query()
                    ->where(function ($query) use ($profile): void {
                        if ($profile->externalId) {
                            $query->where('instagram_user_id', $profile->externalId);
                        }

                        $query->orWhere('username', $profile->username);
                    })
                    ->first() ?? new Creator;
                $signals = $this->markets->detect(implode("\n", array_filter([
                    $profile->bio,
                    $profile->posts->take(12)->pluck('caption')->implode("\n"),
                ])));

                $creator->fill([
                    'instagram_user_id' => $profile->externalId ?: $creator->instagram_user_id,
                    'username' => $profile->username,
                    'display_name' => $profile->displayName ?: $profile->username,
                    'avatar_url' => $profile->avatarUrl,
                    'bio' => $profile->bio,
                    'followers' => $profile->followers,
                    'average_views' => (int) $profile->posts->avg(fn (DiscoveredPost $post): int => $post->views),
                    'average_likes' => (int) $profile->posts->avg(fn (DiscoveredPost $post): int => $post->likes),
                    'niche' => $entry['vertical'],
                    'niche_topics' => array_values($entry['topics']),
                    'primary_vertical' => $entry['vertical'],
                    'market' => $entry['market'],
                    'primary_language' => $signals['language'],
                    'curation_status' => 'approved',
                    'recognition_tier' => $this->eligibility->tier($profile->followers),
                    'is_catalog_seed' => true,
                    'metadata' => array_replace_recursive($creator->metadata ?? [], $profile->metadata, [
                        'catalog' => [
                            'manifest_version' => config('creator_catalog.manifest_version'),
                            'rationale' => $entry['rationale'],
                            'source_urls' => $entry['source_urls'] ?? [],
                            'editorially_verified_at' => $entry['editorially_verified_at'] ?? null,
                        ],
                    ]),
                    'discovered_at' => $creator->discovered_at ?: now(),
                    'last_fetched_at' => now(),
                    'metrics_updated_at' => now(),
                ])->save();

                $this->niches->sync($creator, $entry['vertical'], $entry['topics'], 'catalog');

                return $creator;
            });

            $imported[] = $creator->username;
        }

        $chunks = array_chunk(array_values(array_unique($imported)), 10);
        foreach ($chunks as $handles) {
            MeasureAccountEngagement::dispatch($handles);
        }

        return ['imported' => count($imported), 'skipped' => $skipped, 'jobs' => count($chunks)];
    }
}
