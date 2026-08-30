<?php
namespace Tests\Feature\Content;
use App\Models\ContentPost; use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB; use Tests\TestCase;
class ProbeTest extends TestCase {
    use RefreshDatabase;
    public function test_probe(): void {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('email', 'creator@personal.local')->firstOrFail();
        $a = []; DB::listen(fn ($q) => $a[] = $q->sql);
        $this->actingAs($user)->getJson('/api/feed')->assertOk();
        ContentPost::query()->get()->each(function (ContentPost $p) { $c = $p->replicate(); $c->instagram_media_id = null; $c->save(); });
        $b = []; DB::listen(fn ($q) => $b[] = $q->sql);
        $this->actingAs($user)->getJson('/api/feed')->assertOk();
        $second = array_slice($b, 0);
        file_put_contents('/private/tmp/claude-501/-Users-mohamedchettah-Project-Personal/8456b276-f101-4efa-9be7-3dcbcb3b8df1/scratchpad/q1.txt', implode("\n", array_slice($a, 0, 60)));
        file_put_contents('/private/tmp/claude-501/-Users-mohamedchettah-Project-Personal/8456b276-f101-4efa-9be7-3dcbcb3b8df1/scratchpad/q2.txt', implode("\n", $second));
        $this->assertTrue(true);
    }
}
