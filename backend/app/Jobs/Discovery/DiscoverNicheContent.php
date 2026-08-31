<?php

namespace App\Jobs\Discovery;

use App\Exceptions\ContentDiscoveryException;
use App\Models\Creator;
use App\Models\CreatorRelationship;
use App\Models\DiscoveryQuery;
use App\Models\User;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\NicheExpansionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Finds seed creators from Creator DNA search terms, expands them through
 * Instagram's suggested accounts graph, then queues the existing measurement
 * pipeline to fetch posts and calculate creator-relative outliers.
 */
class DiscoverNicheContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public readonly int $userId,
        public readonly bool $force = false,
    ) {}

    public function handle(NicheExpansionService $expansion, InstagramDataProvider $provider): void
    {
        if (config('creator_catalog.curated_only')) {
            $this->queueCuratedMeasurements();

            return;
        }

        $user = User::query()->with('creatorProfile')->find($this->userId);

        if (! $user) {
            return;
        }

        $queries = $this->dueQueries($expansion->queriesFor($user, $this->force));
        $fallbackNiche = $user->creatorProfile?->niche ?: 'Unclassified';
        $seeds = collect();

        foreach ($queries as $query) {
            try {
                $found = $provider->searchAccounts($query, (int) config('services.discovery.search_results_per_query'));
                $this->markSearched($query);
                $seeds->push(...$found);
            } catch (ContentDiscoveryException $exception) {
                Log::warning('Creator search skipped.', ['query' => $query, 'exception' => $exception]);
            }
        }

        $seeds = $seeds
            ->filter(fn (DiscoveredProfile $profile): bool => ! $profile->isPrivate)
            ->unique(fn (DiscoveredProfile $profile): string => $profile->externalId ?: $profile->username)
            ->take((int) config('services.discovery.seed_limit'))
            ->values();

        // A previous discovery may have stored creators before its measurement
        // job failed. Search cooldowns must not prevent a later refresh from
        // resuming those accounts, otherwise the feed stays empty for days.
        $usernames = collect($this->recoverableHandles($fallbackNiche));

        foreach ($seeds as $seedProfile) {
            $seed = $this->storeCreator($seedProfile, $fallbackNiche);
            $usernames->push($seed->username);

            if (! $seedProfile->externalId) {
                continue;
            }

            try {
                $related = $provider->getRelatedAccounts(
                    $seedProfile->externalId,
                    (int) config('services.discovery.related_per_seed'),
                    $seedProfile->username,
                );
            } catch (ContentDiscoveryException $exception) {
                Log::warning('Related creator expansion skipped.', ['creator' => $seed->username, 'exception' => $exception]);

                continue;
            }

            $this->storeRelated($seed, $related, $fallbackNiche, $usernames);
        }

        $handles = $usernames->filter()->unique()->values()->all();

        if ($handles !== []) {
            MeasureAccountEngagement::dispatch($handles);
        }
    }

    private function queueCuratedMeasurements(): void
    {
        $measurementCutoff = now()->subDays((int) config('services.discovery.measure_cooldown_days'));
        $handles = Creator::query()
            ->where('curation_status', 'approved')
            ->where('safety_status', '!=', 'blocked')
            ->where(function ($query) use ($measurementCutoff): void {
                $query->whereDoesntHave('posts')
                    ->orWhereNull('last_measured_at')
                    ->orWhere('safety_status', 'pending')
                    ->orWhere('last_measured_at', '<=', $measurementCutoff);
            })
            ->orderByRaw('CASE WHEN last_measured_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_measured_at')
            ->limit((int) config('services.discovery.measure_batch'))
            ->pluck('username')
            ->filter()
            ->values();

        $handles
            ->chunk(max(1, (int) config('services.discovery.measure_chunk')))
            ->each(fn (Collection $chunk) => MeasureAccountEngagement::dispatch($chunk->values()->all()));
    }

    /** @return list<string> */
    private function recoverableHandles(string $niche): array
    {
        $measurementCutoff = now()->subDays((int) config('services.discovery.measure_cooldown_days'));

        return Creator::query()
            ->where('niche', $niche)
            ->where('safety_status', '!=', 'blocked')
            ->where(function ($query) use ($measurementCutoff): void {
                $query->whereNull('last_measured_at')
                    ->orWhere('safety_status', 'pending')
                    ->orWhere('last_measured_at', '<=', $measurementCutoff);
            })
            ->orderByRaw('CASE WHEN last_measured_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_measured_at')
            ->limit((int) config('services.discovery.measure_batch'))
            ->pluck('username')
            ->filter()
            ->values()
            ->all();
    }

    /** @param list<string> $queries @return list<string> */
    private function dueQueries(array $queries): array
    {
        $recent = DiscoveryQuery::query()
            ->whereIn('query', $queries)
            ->where('last_searched_at', '>', now()->subDays((int) config('services.discovery.cooldown_days')))
            ->pluck('query')
            ->all();

        return $this->force ? $queries : array_values(array_diff($queries, $recent));
    }

    private function markSearched(string $query): void
    {
        DiscoveryQuery::query()->updateOrCreate(['query' => $query], ['last_searched_at' => now()]);
    }

    private function storeCreator(DiscoveredProfile $profile, string $fallbackNiche): Creator
    {
        $creator = Creator::query()
            ->when($profile->externalId, fn ($query) => $query->where('instagram_user_id', $profile->externalId))
            ->orWhere('username', $profile->username)
            ->first() ?: new Creator;

        $creator->fill(array_filter([
            'instagram_user_id' => $profile->externalId,
            'username' => $profile->username,
            'display_name' => $profile->displayName ?: $profile->username,
            'avatar_url' => $profile->avatarUrl,
            'bio' => $profile->bio,
            'metadata' => array_replace_recursive($creator->metadata ?? [], $profile->metadata),
            'followers' => $profile->followers > 0 ? $profile->followers : ($creator->followers ?: 0),
            'niche' => $creator->exists ? $creator->niche : $fallbackNiche,
            'average_views' => $creator->average_views ?: 0,
            'average_likes' => $creator->average_likes ?: 0,
            'discovered_at' => $creator->discovered_at ?: now(),
        ], fn (mixed $value): bool => $value !== null))->save();

        return $creator;
    }

    /**
     * @param  Collection<int, DiscoveredProfile>  $related
     * @param  Collection<int, string>  $usernames
     */
    private function storeRelated(Creator $seed, Collection $related, string $fallbackNiche, Collection $usernames): void
    {
        $count = max(1, $related->count());

        foreach ($related as $index => $profile) {
            if ($profile->isPrivate) {
                continue;
            }

            $creator = $this->storeCreator($profile, $fallbackNiche);
            $usernames->push($creator->username);

            if ($creator->is($seed)) {
                continue;
            }

            $relationship = CreatorRelationship::query()->firstOrNew([
                'source_creator_id' => $seed->id,
                'related_creator_id' => $creator->id,
                'relationship_type' => 'instagram_suggested',
            ]);
            $relationship->fill([
                'relevance_score' => max(0.2, 1 - ($index / $count)),
                'discovered_at' => $relationship->discovered_at ?: now(),
                'last_seen_at' => now(),
            ])->save();
        }
    }
}
