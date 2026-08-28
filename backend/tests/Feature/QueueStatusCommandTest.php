<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_database_queue_activity_without_exposing_payloads(): void
    {
        CarbonImmutable::setTestNow('2026-08-28 12:00:00 UTC');
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\Instagram\\SyncInstagramAccount'], JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->subMinute()->timestamp,
                'created_at' => now()->subMinutes(2)->timestamp,
            ],
            [
                'queue' => 'analysis',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\Discovery\\AnalyzeCreatorHandle'], JSON_THROW_ON_ERROR),
                'attempts' => 1,
                'reserved_at' => now()->subSeconds(10)->timestamp,
                'available_at' => now()->subMinute()->timestamp,
                'created_at' => now()->subMinutes(3)->timestamp,
            ],
            [
                'queue' => 'remix',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\Content\\GenerateRemix'], JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->addMinute()->timestamp,
                'created_at' => now()->subMinute()->timestamp,
            ],
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => fake()->uuid(),
            'connection' => 'database',
            'queue' => 'analysis',
            'payload' => '{}',
            'exception' => 'Provider timeout containing private diagnostic context.',
            'failed_at' => now(),
        ]);

        $this->artisan('personal:queue-status --details')
            ->expectsTable(
                ['Queue', 'Ready', 'Delayed', 'Reserved', 'Failed', 'Oldest pending (UTC)'],
                [
                    ['analysis', 0, 0, 1, 1, '-'],
                    ['default', 1, 0, 0, 0, '2026-08-28 11:58:00'],
                    ['remix', 0, 1, 0, 0, '2026-08-28 11:59:00'],
                ],
            )
            ->expectsOutput('Current jobs, limited to 25:')
            ->expectsTable(
                ['ID', 'Queue', 'State', 'Job', 'Attempts', 'Created (UTC)'],
                [
                    [2, 'analysis', 'reserved', 'AnalyzeCreatorHandle', 1, '2026-08-28 11:57:00'],
                    [1, 'default', 'ready', 'SyncInstagramAccount', 0, '2026-08-28 11:58:00'],
                    [3, 'remix', 'delayed', 'GenerateRemix', 0, '2026-08-28 11:59:00'],
                ],
            )
            ->doesntExpectOutputToContain('private diagnostic context')
            ->assertSuccessful();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }
}
