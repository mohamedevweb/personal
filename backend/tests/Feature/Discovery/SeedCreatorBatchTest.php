<?php

namespace Tests\Feature\Discovery;

use App\Models\Creator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeedCreatorBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_only_the_selected_rows_and_each_creators_latest_post(): void
    {
        Storage::fake('local');
        $path = $this->csv([
            ['Business', 'FR', '@first.creator', 'First Creator', '100K', 'founder/startup', 'candidate_safe'],
            ['Business', 'UK/US', '@second.creator', 'Second Creator', '200K', 'business/growth', 'candidate_safe_post_filter'],
            ['Tech / AI', 'FR', '@third.creator', 'Third Creator', '300K', 'AI/tools', 'candidate_safe'],
        ]);

        $this->artisan("personal:seed-creator-batch {$path} --batch=1 --size=2 --provider=mock")
            ->assertSuccessful();

        $this->assertDatabaseCount('creators', 2);
        $this->assertDatabaseCount('content_posts', 2);
        $this->assertDatabaseMissing('creators', ['username' => 'third.creator']);

        $first = Creator::query()->where('username', 'first.creator')->firstOrFail();
        $this->assertSame('approved', $first->curation_status);
        $this->assertTrue($first->is_catalog_seed);
        $this->assertSame('FR', $first->market);
        $this->assertSame('Business', data_get($first->metadata, 'seed.vertical'));
        $this->assertSame(['founder', 'startup'], data_get($first->metadata, 'seed.micro_niches'));
        $this->assertCount(1, $first->posts);
        $this->assertSame(
            $first->posts->max('published_at')->toIso8601String(),
            $first->posts->first()->published_at->toIso8601String(),
        );
    }

    public function test_dry_run_never_calls_the_provider_or_writes_rows(): void
    {
        Storage::fake('local');
        $path = $this->csv([
            ['Business', 'FR', '@first.creator', 'First Creator', '100K', 'founder/startup', 'candidate_safe'],
        ]);

        $this->artisan("personal:seed-creator-batch {$path} --dry-run")
            ->assertSuccessful();

        $this->assertDatabaseCount('creators', 0);
        $this->assertDatabaseCount('content_posts', 0);
    }

    public function test_repeating_a_batch_does_not_duplicate_the_creator_or_latest_post(): void
    {
        Storage::fake('local');
        $path = $this->csv([
            ['Business', 'FR', '@first.creator', 'First Creator', '100K', 'founder/startup', 'candidate_safe'],
        ]);

        $this->artisan("personal:seed-creator-batch {$path} --provider=mock")->assertSuccessful();
        $this->artisan("personal:seed-creator-batch {$path} --provider=mock")->assertSuccessful();

        $this->assertDatabaseCount('creators', 1);
        $this->assertDatabaseCount('content_posts', 1);
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): string
    {
        $contents = collect([
            ['Verticale', 'Marché', 'Handle', 'Créateur', 'Followers ≈', 'Micro-niches', 'Safety'],
            ...$rows,
        ])->map(fn (array $row): string => implode(',', $row))->implode("\n");
        Storage::disk('local')->put('creator-seed.csv', $contents);

        return Storage::disk('local')->path('creator-seed.csv');
    }
}
