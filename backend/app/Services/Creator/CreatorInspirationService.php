<?php

namespace App\Services\Creator;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use App\Models\User;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\CreatorScrapeSchedule;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProviderManager;
use App\Services\Feed\CreatorAffinity;
use App\Services\Instagram\InstagramMediaProxy;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatorInspirationService
{
    private const SUGGESTION_LIMIT = 6;

    public const MINIMUM_SELECTION = 3;

    public const MAXIMUM_SELECTION = 6;

    public function __construct(
        private readonly InstagramDataProviderManager $providers,
        private readonly CanonicalCreatorVerticals $verticals,
        private readonly CreatorAffinity $affinity,
        private readonly InstagramMediaProxy $media,
        private readonly CreatorScrapeSchedule $scrapeSchedule,
    ) {}

    /** @return array{selected: list<array<string, mixed>>, suggestions: list<array<string, mixed>>, suggestion_limit: int, minimum: int, maximum: int} */
    public function forUser(User $user): array
    {
        $selected = $user->inspirationCreators()
            ->where(function ($query) use ($user): void {
                $query->whereNull('creators.user_id')->orWhere('creators.user_id', '!=', $user->id);
            })
            ->get();
        $selectedIds = $selected->pluck('id');
        $market = $user->creatorProfile?->market;

        $pool = Creator::query()
            ->where('curation_status', 'approved')
            ->where('safety_status', ContentSafetyDecision::ALLOWED)
            ->where(function ($query) use ($user): void {
                $query->whereNull('user_id')->orWhere('user_id', '!=', $user->id);
            })
            ->whereNotIn('id', $selectedIds)
            ->orderByDesc('followers')
            ->get();

        $suggestions = $pool
            ->sortByDesc(fn (Creator $creator): float => (($this->affinity->score($user->creatorProfile, $creator) ?? 0) * 100)
                + ($market && $creator->market === $market ? 10 : 0)
                + ($creator->is_catalog_seed ? 1 : 0)
            )
            // The client keeps only six cards visible. The reserve lets it replace
            // every locally selected creator without another provider request.
            ->take(self::SUGGESTION_LIMIT + self::MAXIMUM_SELECTION)
            ->values();

        return [
            'selected' => $selected->map(fn (Creator $creator): array => $this->render($creator, true))->all(),
            'suggestions' => $suggestions->map(fn (Creator $creator): array => $this->render($creator, false))->all(),
            'suggestion_limit' => self::SUGGESTION_LIMIT,
            'minimum' => self::MINIMUM_SELECTION,
            'maximum' => self::MAXIMUM_SELECTION,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function search(User $user, string $query): array
    {
        $exactHandle = $this->handleFromInput($query);
        $selected = $user->inspirationCreators()->pluck('creators.id')->flip();
        $local = Creator::query()
            ->where('safety_status', '!=', ContentSafetyDecision::BLOCKED)
            ->where(function ($builder) use ($user): void {
                $builder->whereNull('user_id')->orWhere('user_id', '!=', $user->id);
            })
            ->whereRaw('LOWER(username) = ?', [Str::lower($exactHandle)])
            ->first();

        if ($local?->avatar_url) {
            return [$this->render($local, $selected->has($local->id))];
        }

        $profile = $this->providers->provider()->getProfile($exactHandle);

        if (! $profile
            || $profile->isPrivate
            || Str::lower($profile->username) !== Str::lower($exactHandle)
            || Str::lower($profile->username) === Str::lower($this->ownInstagramUsername($user))) {
            return $local ? [$this->render($local, $selected->has($local->id))] : [];
        }

        Cache::put(
            $this->profileCacheKey($profile->username),
            $profile,
            now()->addHours(max(1, (int) config('services.instagram_media_proxy.signature_hours'))),
        );

        return [$this->renderProfile($profile, $local ? $selected->has($local->id) : false)];
    }

    public function previewAvatarResponse(string $username): ?Response
    {
        $profile = Cache::get($this->profileCacheKey($username));

        if (! $profile instanceof DiscoveredProfile || ! $profile->avatarUrl) {
            return null;
        }

        return $this->media->response(
            $profile->avatarUrl,
            'creator-preview:'.Str::lower($profile->username),
        );
    }

    /** @param list<string> $inputs @return list<array<string, mixed>> */
    public function select(User $user, array $inputs): array
    {
        $handles = collect($inputs)
            ->map(fn (string $input): ?string => $this->handleFromInput($input))
            ->filter()
            ->unique(fn (string $handle): string => Str::lower($handle))
            ->values();

        if ($handles->count() < self::MINIMUM_SELECTION || $handles->count() > self::MAXIMUM_SELECTION) {
            throw ValidationException::withMessages([
                'handles' => ['Choose between '.self::MINIMUM_SELECTION.' and '.self::MAXIMUM_SELECTION.' Instagram creators.'],
            ]);
        }

        $creators = $handles->map(fn (string $handle): Creator => $this->resolveCreator($user, $handle));

        $previousCreatorIds = $user->inspirationCreators()->pluck('creators.id');

        DB::transaction(function () use ($user, $creators): void {
            $sync = $creators->values()->mapWithKeys(
                fn (Creator $creator, int $priority): array => [$creator->id => ['priority' => $priority]],
            )->all();

            $user->inspirationCreators()->sync($sync);
        });

        Creator::query()
            ->whereIn('id', $previousCreatorIds->merge($creators->pluck('id'))->unique())
            ->get()
            ->each(fn (Creator $creator) => $this->scrapeSchedule->reprioritize($creator, now()));

        $due = $creators
            ->filter(fn (Creator $creator): bool => $creator->safety_status !== ContentSafetyDecision::BLOCKED
                && (! $creator->last_measured_at || ! $creator->posts()->exists()))
            ->pluck('username')
            ->unique()
            ->values();

        $due->chunk(10)->each(fn (Collection $chunk) => MeasureAccountEngagement::dispatch($chunk->all()));

        return $user->inspirationCreators()->get()
            ->map(fn (Creator $creator): array => $this->render($creator, true))
            ->all();
    }

    private function resolveCreator(User $user, string $handle): Creator
    {
        if (Str::lower($handle) === Str::lower($this->ownInstagramUsername($user))) {
            throw ValidationException::withMessages(['handles' => ['Your own Instagram account cannot be selected.']]);
        }

        $existing = Creator::query()->whereRaw('LOWER(username) = ?', [Str::lower($handle)])->first();

        if ($existing?->user_id === $user->id || $existing?->safety_status === ContentSafetyDecision::BLOCKED) {
            throw ValidationException::withMessages(['handles' => ["@{$handle} cannot be selected."]]);
        }

        if ($existing) {
            return $existing;
        }

        $profile = Cache::get($this->profileCacheKey($handle)) ?: $this->providers->provider()->getProfile($handle);

        if (! $profile || $profile->isPrivate) {
            throw ValidationException::withMessages(['handles' => ["@{$handle} is unavailable or private."]]);
        }

        $vertical = $this->primaryVertical($user) ?? 'unclassified';
        $creator = Creator::query()
            ->when($profile->externalId, fn ($query) => $query->where('instagram_user_id', $profile->externalId))
            ->orWhereRaw('LOWER(username) = ?', [Str::lower($profile->username)])
            ->first() ?? new Creator;
        $isNew = ! $creator->exists;
        $attributes = [
            'instagram_user_id' => $profile->externalId ?: $creator->instagram_user_id,
            'username' => $profile->username,
            'display_name' => $profile->displayName ?: $profile->username,
            'avatar_url' => $profile->avatarUrl,
            'bio' => $profile->bio,
            'followers' => $profile->followers,
            'average_views' => (int) $profile->posts->avg(fn ($post): int => $post->views),
            'average_likes' => (int) $profile->posts->avg(fn ($post): int => $post->likes),
            'metadata' => array_replace_recursive($creator->metadata ?? [], $profile->metadata, [
                'inspirations' => ['first_selected_at' => now()->toIso8601String()],
            ]),
            'discovered_at' => $creator->discovered_at ?: now(),
            'last_fetched_at' => now(),
        ];

        if ($isNew) {
            $attributes += [
                'niche' => $vertical,
                'niche_topics' => [],
                'market' => $user->creatorProfile?->market,
                'primary_language' => 'unknown',
                'curation_status' => 'discovered',
                'is_catalog_seed' => false,
                'baseline_engagement' => 0,
                'safety_status' => ContentSafetyDecision::PENDING,
                'safety_reasons' => [],
            ];
        }

        $creator->fill($attributes)->save();

        return $creator;
    }

    private function handleFromInput(string $input, bool $allowKeywords = false): ?string
    {
        $input = trim($input);
        $candidate = ltrim($input, '@');

        if (filter_var($input, FILTER_VALIDATE_URL)) {
            $host = Str::lower((string) parse_url($input, PHP_URL_HOST));
            if (! in_array($host, ['instagram.com', 'www.instagram.com'], true)) {
                throw ValidationException::withMessages(['handles' => ['Use an Instagram profile link.']]);
            }

            $candidate = explode('/', trim((string) parse_url($input, PHP_URL_PATH), '/'))[0] ?? '';
        }

        if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $candidate) === 1) {
            return $candidate;
        }

        if ($allowKeywords) {
            return null;
        }

        throw ValidationException::withMessages(['handles' => ["{$input} is not a valid Instagram handle."]]);
    }

    /** @return array<string, mixed> */
    private function render(Creator $creator, bool $selected): array
    {
        return [
            'username' => $creator->username,
            'display_name' => $creator->display_name,
            'avatar_url' => $this->avatarUrl($creator),
            'followers' => $creator->followers,
            'niche' => $creator->niche,
            'is_selected' => $selected,
            'is_measured' => (bool) $creator->last_measured_at,
        ];
    }

    /** @return array<string, mixed> */
    private function renderProfile(DiscoveredProfile $profile, bool $selected = false): array
    {
        return [
            'username' => $profile->username,
            'display_name' => $profile->displayName ?: $profile->username,
            'avatar_url' => $this->previewAvatarUrl($profile),
            'followers' => $profile->followers,
            'niche' => null,
            'is_selected' => $selected,
            'is_measured' => false,
        ];
    }

    private function avatarUrl(Creator $creator): ?string
    {
        if (! $creator->avatar_url || ! $this->media->supports($creator->avatar_url)) {
            return $creator->avatar_url;
        }

        $path = URL::temporarySignedRoute(
            'media.creator',
            now()->addHours((int) config('services.instagram_media_proxy.signature_hours')),
            ['creator' => $creator->id],
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').$path;
    }

    private function previewAvatarUrl(DiscoveredProfile $profile): ?string
    {
        if (! $profile->avatarUrl || ! $this->media->supports($profile->avatarUrl)) {
            return $profile->avatarUrl;
        }

        $path = URL::temporarySignedRoute(
            'media.creator-preview',
            now()->addHours(max(1, (int) config('services.instagram_media_proxy.signature_hours'))),
            ['username' => $profile->username],
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').$path;
    }

    private function primaryVertical(User $user): ?string
    {
        return $this->verticals->canonical($user->creatorProfile?->primary_vertical)
            ?? $this->verticals->fromSignals([
                $user->creatorProfile?->niche,
                ...($user->creatorProfile?->topics ?? []),
            ]);
    }

    private function profileCacheKey(string $username): string
    {
        return 'creator-inspiration-profile:'.Str::lower($username);
    }

    private function ownInstagramUsername(User $user): string
    {
        return (string) ($user->instagramAccount?->username ?? $user->creatorProfile?->instagram_username);
    }
}
