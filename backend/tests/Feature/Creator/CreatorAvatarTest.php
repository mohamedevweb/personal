<?php

namespace Tests\Feature\Creator;

use App\Jobs\Discovery\RefreshCreatorAvatar;
use App\Models\CreatorProfile;
use App\Models\User;
use App\Services\Discovery\InstagramDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The picture a creator who only gave their handle sees on their memory page: it
 * comes from their public profile and is served through the signed media proxy.
 */
class CreatorAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_memory_page_serves_the_public_profile_picture_through_the_proxy(): void
    {
        Queue::fake();
        config(['app.url' => 'https://api.personal.test']);
        Storage::fake('local');
        Http::preventStrayRequests();
        $source = 'https://scontent-sea5-1.cdninstagram.com/handle-avatar.jpg';
        Http::fake([$source => Http::response('avatar-content', 200, ['Content-Type' => 'image/jpeg'])]);
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
            'avatar_url' => $source,
        ]);

        $avatar = $this->actingAs($user)->getJson('/api/me/profile')->assertOk()->json('profile.avatar_url');

        $this->assertIsString($avatar);
        $this->assertStringStartsWith('https://api.personal.test/api/media/creator-profile/', $avatar);
        $this->assertStringContainsString('signature=', $avatar);
        // The same picture stands in for the account avatar everywhere else.
        $this->assertSame($avatar, $this->actingAs($user)->getJson('/api/auth/me')->assertOk()->json('user.avatar_url'));

        $path = parse_url($avatar, PHP_URL_PATH).'?'.parse_url($avatar, PHP_URL_QUERY);
        $this->get($path)
            ->assertOk()
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->assertContent('avatar-content');
    }

    public function test_a_handle_read_before_the_picture_was_kept_is_filled_in_behind_the_page(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
            'analysis_status' => 'completed',
        ]);

        $this->actingAs($user)->getJson('/api/me/profile')->assertOk()->assertJsonPath('profile.avatar_url', null);
        // Opening the page again inside the hour does not scrape again.
        $this->actingAs($user)->getJson('/api/me/profile')->assertOk();

        Queue::assertPushed(RefreshCreatorAvatar::class, 1);
    }

    public function test_the_refresh_saves_the_picture_on_the_public_profile(): void
    {
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
        ]);

        (new RefreshCreatorAvatar($user->id))->handle(app(InstagramDataProvider::class));

        $this->assertNotNull($user->creatorProfile()->firstOrFail()->avatar_url);
    }
}
