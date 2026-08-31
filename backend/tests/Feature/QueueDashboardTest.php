<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QueueDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_read_queue_metadata_without_payloads(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        config([
            'app.queue_dashboard_emails' => ['admin@example.com'],
            'queue.default' => 'database',
        ]);
        Sanctum::actingAs($user);

        DB::table('jobs')->insert([
            'queue' => 'analysis',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\Discovery\\AnalyzeCreatorHandle'], JSON_THROW_ON_ERROR),
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $response = $this->getJson('/api/admin/queues');

        $response->assertOk()
            ->assertJsonPath('queues.0.queue', 'analysis')
            ->assertJsonPath('queues.0.reserved', 1)
            ->assertJsonPath('jobs.0.job', 'AnalyzeCreatorHandle')
            ->assertJsonMissing(['payload' => ''])
            ->assertJsonMissingPath('jobs.0.payload');
    }

    public function test_a_non_admin_cannot_read_the_queue_dashboard(): void
    {
        config(['app.queue_dashboard_emails' => ['admin@example.com']]);
        Sanctum::actingAs(User::factory()->create(['email' => 'creator@example.com']));

        $this->getJson('/api/admin/queues')->assertNotFound();
    }
}
