<?php

namespace Tests\Feature\Discovery;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshValidatedCreatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_only_approved_creators_for_the_requested_vertical(): void
    {
        Queue::fake();

        $target = Creator::query()->create([
            'username' => 'tech.target',
            'display_name' => 'Tech Target',
            'niche' => 'tech-ai',
            'primary_vertical' => 'tech-ai',
            'followers' => 10_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'market' => 'FR',
            'curation_status' => 'approved',
            'safety_status' => 'allowed',
        ]);
        $otherVertical = Creator::query()->create([
            'username' => 'food.other',
            'display_name' => 'Food Other',
            'niche' => 'food-cooking',
            'primary_vertical' => 'food-cooking',
            'followers' => 10_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'market' => 'FR',
            'curation_status' => 'approved',
            'safety_status' => 'allowed',
        ]);
        Creator::query()->create([
            'username' => 'tech.discovered',
            'display_name' => 'Tech Discovered',
            'niche' => 'tech-ai',
            'primary_vertical' => 'tech-ai',
            'followers' => 10_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'market' => 'FR',
            'curation_status' => 'discovered',
            'safety_status' => 'allowed',
        ]);

        $this->artisan('personal:refresh-validated-creators', [
            '--vertical' => ['tech-ai'],
            '--posts' => 30,
        ])->assertSuccessful();

        Queue::assertPushed(MeasureAccountEngagement::class, 1);
        Queue::assertPushed(
            MeasureAccountEngagement::class,
            fn (MeasureAccountEngagement $job): bool => $job->usernames === [$target->username]
                && $job->force
                && $job->recentOnly
                && $job->postsLimit === 30
                && $job->marketHints === ['tech.target' => 'FR'],
        );
        Queue::assertNotPushed(
            MeasureAccountEngagement::class,
            fn (MeasureAccountEngagement $job): bool => in_array($otherVertical->username, $job->usernames, true),
        );
    }

    public function test_it_can_preview_the_refresh_without_queueing_jobs(): void
    {
        Queue::fake();
        Creator::query()->create([
            'username' => 'preview.creator',
            'display_name' => 'Preview Creator',
            'niche' => 'tech-ai',
            'primary_vertical' => 'tech-ai',
            'followers' => 10_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'market' => 'FR',
            'curation_status' => 'approved',
            'safety_status' => 'allowed',
        ]);

        $this->artisan('personal:refresh-validated-creators', ['--dry-run' => true])
            ->expectsOutput('Would refresh 1 validated creators.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
