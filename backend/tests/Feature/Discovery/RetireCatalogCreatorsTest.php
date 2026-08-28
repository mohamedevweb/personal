<?php

namespace Tests\Feature\Discovery;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\Remix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetireCatalogCreatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_seeds_the_manifest_no_longer_approves(): void
    {
        $approved = $this->creator('tiboinshape');
        $retired = $this->creator('sissymua');
        $this->publication($approved);
        $this->publication($retired);

        $this->artisan('personal:retire-catalog-creators')->assertSuccessful();

        $this->assertDatabaseHas('creators', ['username' => 'tiboinshape', 'curation_status' => 'approved']);
        $this->assertDatabaseMissing('creators', ['username' => 'sissymua']);
        $this->assertSame(1, ContentPost::query()->count());
        $this->assertSame(0, ContentPost::query()->where('creator_id', $retired->id)->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $retired = $this->creator('sissymua');
        $this->publication($retired);

        $this->artisan('personal:retire-catalog-creators', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('creators', ['username' => 'sissymua', 'curation_status' => 'approved']);
        $this->assertSame(1, ContentPost::query()->count());
    }

    public function test_it_never_touches_a_member_identity_or_a_discovered_creator(): void
    {
        $member = $this->creator('member');
        $member->update(['user_id' => User::factory()->create()->id]);
        $discovered = $this->creator('discovered');
        $discovered->update(['is_catalog_seed' => false, 'curation_status' => 'discovered']);

        $this->artisan('personal:retire-catalog-creators')->assertSuccessful();

        $this->assertDatabaseHas('creators', ['username' => 'member']);
        $this->assertDatabaseHas('creators', ['username' => 'discovered']);
    }

    public function test_it_keeps_a_remixed_post_and_stops_it_instead(): void
    {
        $retired = $this->creator('sissymua');
        $kept = $this->publication($retired);
        $dropped = $this->publication($retired);
        Remix::query()->create([
            'user_id' => User::factory()->create()->id,
            'source_content_id' => $kept->id,
            'format' => 'reel',
            'generated_content' => ['hook' => 'kept'],
        ]);

        $this->artisan('personal:retire-catalog-creators')->assertSuccessful();

        // The member's library survives the editorial decision; the post simply
        // loses every score that could put it back in a feed.
        $this->assertDatabaseMissing('content_posts', ['id' => $dropped->id]);
        $this->assertDatabaseHas('content_posts', [
            'id' => $kept->id,
            'tracking_status' => 'stopped',
            'outlier_score' => 0,
        ]);
        $this->assertDatabaseHas('creators', ['username' => 'sissymua', 'curation_status' => 'inactive']);
    }

    private function creator(string $username): Creator
    {
        return Creator::query()->create([
            'username' => $username,
            'display_name' => str($username)->headline(),
            'niche' => 'sport-fitness',
            'market' => 'FR',
            'curation_status' => 'approved',
            'safety_status' => 'allowed',
            'followers' => 100000,
            'average_views' => 10000,
            'average_likes' => 1000,
            'baseline_engagement' => 700,
            'is_catalog_seed' => true,
        ]);
    }

    private function publication(Creator $creator): ContentPost
    {
        return ContentPost::query()->create([
            'creator_id' => $creator->id,
            'format' => 'reel',
            'hook' => 'hook',
            'caption' => 'caption',
            'views' => 10000,
            'likes' => 900,
            'comments' => 100,
            'published_at' => now()->subDays(3),
            'measured_at' => now()->subDay(),
            'outlier_score' => 2.5,
            'safety_status' => 'allowed',
            'tracking_status' => 'active',
        ]);
    }
}
