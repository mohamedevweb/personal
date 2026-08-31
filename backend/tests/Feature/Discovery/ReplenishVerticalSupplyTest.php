<?php

namespace Tests\Feature\Discovery;

use App\Jobs\Discovery\DiscoverNicheContent;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReplenishVerticalSupplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discovery.vertical_supply.minimum_posts' => 3,
            'services.discovery.vertical_supply.minimum_creators' => 2,
            'services.discovery.vertical_supply.batch' => 20,
            'services.discovery.min_followers' => 5_000,
            'services.discovery.min_post_engagement' => 500,
        ]);
    }

    public function test_it_replenishes_only_the_requested_vertical(): void
    {
        Bus::fake();

        $events = $this->profile('events');
        $this->profile('events');
        $travel = $this->profile('travel');

        $this->artisan('personal:replenish-vertical-supply', ['--vertical' => 'events'])
            ->assertSuccessful();

        Bus::assertDispatched(
            DiscoverNicheContent::class,
            fn (DiscoverNicheContent $job): bool => $job->userId === $events->user_id && $job->force,
        );
        Bus::assertDispatchedTimes(DiscoverNicheContent::class, 1);
        Bus::assertNotDispatched(
            DiscoverNicheContent::class,
            fn (DiscoverNicheContent $job): bool => $job->userId === $travel->user_id,
        );
    }

    public function test_it_does_not_replenish_a_vertical_that_has_enough_inventory(): void
    {
        Bus::fake();
        $profile = $this->profile('events');
        $first = $this->creator('event-one', 'events');
        $second = $this->creator('event-two', 'events');

        $this->storePost($first, 'event-one-a');
        $this->storePost($first, 'event-one-b');
        $this->storePost($second, 'event-two-a');

        $this->artisan('personal:replenish-vertical-supply', ['--vertical' => 'events'])
            ->assertSuccessful();

        Bus::assertNotDispatched(DiscoverNicheContent::class);
        $this->assertModelExists($profile);
    }

    private function profile(string $vertical): CreatorProfile
    {
        return CreatorProfile::query()->create([
            'user_id' => User::factory()->create()->id,
            'niche' => $vertical,
            'primary_vertical' => $vertical,
            'topics' => [$vertical],
        ]);
    }

    private function creator(string $username, string $vertical): Creator
    {
        return Creator::query()->create([
            'username' => $username,
            'display_name' => $username,
            'niche' => $vertical,
            'primary_vertical' => $vertical,
            'market' => 'FR',
            'followers' => 10_000,
            'average_views' => 5_000,
            'average_likes' => 700,
            'baseline_engagement' => 700,
            'safety_status' => 'allowed',
        ]);
    }

    private function storePost(Creator $creator, string $hook): ContentPost
    {
        return ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => "https://www.instagram.com/p/{$hook}/",
            'platform' => 'instagram',
            'format' => 'reel',
            'hook' => $hook,
            'caption' => 'A useful event post',
            'views' => 10_000,
            'likes' => 700,
            'comments' => 0,
            'published_at' => now()->subDay(),
            'performance_ratio' => 1.3,
            'outlier_score' => 1.3,
            'engagement_rate' => 7.0,
            'measured_at' => now(),
            'safety_status' => 'allowed',
            'why_it_works' => '',
            'hook_analysis' => '',
            'structure_analysis' => '',
        ]);
    }
}
