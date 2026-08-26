<?php

namespace Tests\Feature\Creator;

use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Services\Creator\RegisteredCreatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisteredCreatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_instagram_member_is_stored_as_a_private_pending_creator_identity(): void
    {
        [$user, $account, $profile] = $this->member();

        $creator = app(RegisteredCreatorService::class)->sync($account->fresh('media'), $profile);

        $this->assertSame($user->id, $creator->user_id);
        $this->assertSame('instagram-member', $creator->instagram_user_id);
        $this->assertSame('member_creator', $creator->username);
        $this->assertSame('tech-ai', $creator->niche);
        $this->assertSame('discovered', $creator->curation_status);
        $this->assertSame('pending', $creator->safety_status);
        $this->assertSame(1900, $creator->average_views);
        $this->assertSame(84, $creator->average_likes);
        $this->assertTrue($user->creatorIdentity()->firstOrFail()->is($creator));
        $this->assertDatabaseHas('content_posts', [
            'creator_id' => $creator->id,
            'instagram_media_id' => 'member-media',
            'format' => 'reel',
            'views' => 1900,
        ]);
        $this->actingAs($user)
            ->getJson('/api/me/posts')
            ->assertOk()
            ->assertJsonCount(1, 'posts')
            ->assertJsonPath('posts.0.instagram_media_id', null)
            ->assertJsonPath('posts.0.views', 1900);
    }

    public function test_existing_catalog_creator_is_linked_without_losing_editorial_state(): void
    {
        [$user, $account, $profile] = $this->member();
        $existing = Creator::query()->create([
            'instagram_user_id' => 'instagram-member',
            'username' => 'old_handle',
            'display_name' => 'Catalog Creator',
            'niche' => 'personal-branding',
            'market' => 'FR',
            'followers' => 100000,
            'average_views' => 10000,
            'average_likes' => 1000,
            'curation_status' => 'approved',
            'safety_status' => 'allowed',
            'is_catalog_seed' => true,
        ]);

        $creator = app(RegisteredCreatorService::class)->sync($account->fresh('media'), $profile);

        $this->assertTrue($creator->is($existing));
        $this->assertSame($user->id, $creator->user_id);
        $this->assertSame('member_creator', $creator->username);
        $this->assertSame('personal-branding', $creator->niche);
        $this->assertSame('approved', $creator->curation_status);
        $this->assertSame('allowed', $creator->safety_status);
        $this->assertTrue($creator->is_catalog_seed);
        $this->assertDatabaseCount('creators', 1);
    }

    public function test_command_backfills_existing_members_without_provider_calls(): void
    {
        [$user] = $this->member();

        $this->artisan('personal:link-registered-creators')
            ->expectsOutput('Linked 1 registered creators; skipped 0 accounts without a profile.')
            ->assertSuccessful();

        $this->assertDatabaseHas('creators', [
            'user_id' => $user->id,
            'instagram_user_id' => 'instagram-member',
        ]);
    }

    /** @return array{User, InstagramAccount, CreatorProfile} */
    private function member(): array
    {
        $user = User::factory()->create();
        $account = InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => 'instagram-member',
            'username' => 'member_creator',
            'display_name' => 'Member Creator',
            'bio' => 'Créateur tech et SaaS',
            'followers_count' => 42000,
            'access_token' => 'secret',
            'connected_at' => now(),
        ]);
        $account->media()->create([
            'instagram_media_id' => 'member-media',
            'media_type' => 'VIDEO',
            'like_count' => 84,
            'comments_count' => 12,
            'metrics' => ['views' => 1900],
            'synced_at' => now(),
        ]);
        $profile = CreatorProfile::query()->create([
            'user_id' => $user->id,
            'primary_vertical' => 'tech-ai',
            'market' => 'FR',
            'topics' => ['SaaS'],
            'creator_dna' => ['language' => 'fr'],
        ]);

        return [$user, $account, $profile];
    }
}
