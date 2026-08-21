<?php

namespace App\Console\Commands;

use App\Exceptions\ContentDiscoveryException;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CompareInstagramProviders extends Command
{
    protected $signature = 'personal:compare-instagram-providers
        {query : Niche or creator search phrase}
        {--provider=* : Limit the run to hiker or scrapecreators}
        {--creators=5 : Creator profiles to inspect per provider}
        {--posts=12 : Recent posts to inspect per creator}';

    protected $description = 'Compare HikerAPI and ScrapeCreators on the same Instagram discovery query';

    public function handle(InstagramDataProviderManager $providers): int
    {
        $query = trim((string) $this->argument('query'));
        $creatorLimit = max(1, min(20, (int) $this->option('creators')));
        $postLimit = max(1, min(50, (int) $this->option('posts')));
        $selected = $this->selectedProviders();

        if ($query === '' || $selected === null) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Query: {$query}");
        $this->line("Inspecting up to {$creatorLimit} creators and {$postLimit} recent posts per creator.");

        $results = collect($selected)->mapWithKeys(function (string $driver) use ($providers, $query, $creatorLimit, $postLimit): array {
            return [$driver => $this->benchmark($providers, $driver, $query, $creatorLimit, $postLimit)];
        });

        $this->newLine();
        $this->table(
            ['Provider', 'Creators', 'Reels', 'Follower data', 'View data', 'Failures', 'Time'],
            $results->map(fn (array $result, string $driver): array => [
                $this->label($driver),
                $result['creators']->count(),
                $result['posts']->where('format', 'reel')->count(),
                $result['profiles']->where('followers', '>', 0)->count(),
                $result['posts']->where('views', '>', 0)->count(),
                $result['failures'],
                number_format($result['seconds'], 2).'s',
            ])->values()->all(),
        );

        foreach ($results as $driver => $result) {
            $this->showSample($driver, $result);
        }

        return $results->contains(fn (array $result): bool => $result['error'] !== null)
            ? self::FAILURE
            : self::SUCCESS;
    }

    /** @return list<string>|null */
    private function selectedProviders(): ?array
    {
        $selected = array_values(array_unique(array_filter((array) $this->option('provider'))));
        $selected = $selected === [] ? ['hiker', 'scrapecreators'] : $selected;
        $invalid = array_diff($selected, ['hiker', 'scrapecreators']);

        if ($invalid !== []) {
            $this->error('Supported comparison providers are hiker and scrapecreators.');

            return null;
        }

        return $selected;
    }

    /** @return array{creators: Collection<int, DiscoveredProfile>, profiles: Collection<int, DiscoveredProfile>, posts: Collection<int, DiscoveredPost>, failures: int, seconds: float, error: ?string} */
    private function benchmark(
        InstagramDataProviderManager $providers,
        string $driver,
        string $query,
        int $creatorLimit,
        int $postLimit,
    ): array {
        $started = hrtime(true);
        $creators = collect();
        $profiles = collect();
        $posts = collect();
        $failures = 0;
        $error = null;

        try {
            $provider = $providers->provider($driver);
            $creators = $provider->searchAccounts($query, $creatorLimit);

            foreach ($creators as $creator) {
                try {
                    $profile = $provider->getProfile($creator->username) ?: $creator;
                    $profiles->push($profile);
                    $recent = $profile->posts->isNotEmpty()
                        ? $profile->posts->take($postLimit)
                        : $provider->getPosts($profile->username, $postLimit, $profile->externalId);
                    $posts->push(...$recent);
                } catch (ContentDiscoveryException $exception) {
                    $failures++;
                    $this->warn($this->label($driver).' skipped @'.$creator->username.': '.$exception->getMessage());
                }
            }
        } catch (ContentDiscoveryException $exception) {
            $error = $exception->getMessage();
            $this->error($this->label($driver).': '.$error);
        }

        return [
            'creators' => $creators,
            'profiles' => $profiles,
            'posts' => $posts
                ->unique(fn (DiscoveredPost $post): string => $post->externalId ?: $post->sourceUrl)
                ->values(),
            'failures' => $failures,
            'seconds' => (hrtime(true) - $started) / 1_000_000_000,
            'error' => $error,
        ];
    }

    /** @param array{creators: Collection<int, DiscoveredProfile>, profiles: Collection<int, DiscoveredProfile>, posts: Collection<int, DiscoveredPost>, failures: int, seconds: float, error: ?string} $result */
    private function showSample(string $driver, array $result): void
    {
        $this->newLine();
        $this->info($this->label($driver));

        if ($result['error']) {
            $this->line($result['error']);

            return;
        }

        $profiles = $result['profiles']->isNotEmpty() ? $result['profiles'] : $result['creators'];
        $this->line('Creator sample');
        $this->table(
            ['Handle', 'Followers', 'Name', 'Bio'],
            $profiles->take(5)->map(fn (DiscoveredProfile $profile): array => [
                '@'.$profile->username,
                number_format($profile->followers),
                $profile->displayName ?: '',
                Str::limit(preg_replace('/\s+/', ' ', $profile->bio ?: '') ?: '', 90),
            ])->all(),
        );

        $this->line('Content sample, sorted by views');
        $this->table(
            ['Creator', 'Type', 'Views', 'Engagement', 'Published', 'Caption', 'URL'],
            $result['posts']
                ->sortByDesc(fn (DiscoveredPost $post): int => $post->views)
                ->take(8)
                ->map(fn (DiscoveredPost $post): array => [
                    '@'.$post->username,
                    $post->format,
                    number_format($post->views),
                    number_format($post->engagement()),
                    $post->publishedAt->toDateString(),
                    Str::limit(preg_replace('/\s+/', ' ', $post->caption) ?: '', 80),
                    $post->sourceUrl,
                ])->all(),
        );
    }

    private function label(string $driver): string
    {
        return match ($driver) {
            'hiker' => 'HikerAPI',
            'scrapecreators' => 'ScrapeCreators',
            default => $driver,
        };
    }
}
