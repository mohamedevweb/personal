<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final readonly class QueueStatusService
{
    /**
     * Return queue metadata only. Payloads and exception traces never leave the
     * backend, even for the internal dashboard.
     *
     * @return array{generated_at: string, queues: list<array<string, mixed>>, jobs: list<array<string, mixed>>}
     */
    public function snapshot(int $limit = 50): array
    {
        $connectionName = (string) config('queue.default');
        $queueConfig = config("queue.connections.{$connectionName}");

        if (! is_array($queueConfig) || ($queueConfig['driver'] ?? null) !== 'database') {
            throw new RuntimeException("The [{$connectionName}] queue connection is not database-backed.");
        }

        $connection = DB::connection($queueConfig['connection'] ?? null);
        $jobsTable = (string) ($queueConfig['table'] ?? 'jobs');
        $now = now()->timestamp;

        $ready = $this->countsByQueue(
            $connection,
            $jobsTable,
            fn ($query) => $query->whereNull('reserved_at')->where('available_at', '<=', $now),
        );
        $delayed = $this->countsByQueue(
            $connection,
            $jobsTable,
            fn ($query) => $query->whereNull('reserved_at')->where('available_at', '>', $now),
        );
        $reserved = $this->countsByQueue(
            $connection,
            $jobsTable,
            fn ($query) => $query->whereNotNull('reserved_at'),
        );
        $oldestPending = $connection->table($jobsTable)
            ->whereNull('reserved_at')
            ->groupBy('queue')
            ->selectRaw('queue, MIN(created_at) AS oldest_created_at')
            ->pluck('oldest_created_at', 'queue');
        $failed = $this->failedCountsByQueue();

        $queues = $ready->keys()
            ->merge($delayed->keys())
            ->merge($reserved->keys())
            ->merge($failed->keys())
            ->unique()
            ->sort()
            ->values();

        $jobs = $connection->table($jobsTable)
            ->orderByRaw('reserved_at IS NULL')
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->map(fn (object $job): array => [
                'id' => (int) $job->id,
                'queue' => (string) $job->queue,
                'state' => $job->reserved_at !== null
                    ? 'reserved'
                    : ((int) $job->available_at > $now ? 'delayed' : 'ready'),
                'job' => $this->jobName((string) $job->payload),
                'attempts' => (int) $job->attempts,
                'created_at' => $this->timestamp($job->created_at),
            ])
            ->values()
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'queues' => $queues->map(fn (string $queue): array => [
                'queue' => $queue,
                'ready' => (int) $ready->get($queue, 0),
                'delayed' => (int) $delayed->get($queue, 0),
                'reserved' => (int) $reserved->get($queue, 0),
                'failed' => (int) $failed->get($queue, 0),
                'oldest_pending_at' => $this->nullableTimestamp($oldestPending->get($queue)),
            ])->all(),
            'jobs' => $jobs,
        ];
    }

    private function countsByQueue(ConnectionInterface $connection, string $table, callable $scope): Collection
    {
        $query = $connection->table($table);
        $scope($query);

        return $query
            ->groupBy('queue')
            ->selectRaw('queue, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'queue');
    }

    private function failedCountsByQueue(): Collection
    {
        if (config('queue.failed.driver') !== 'database-uuids') {
            return collect();
        }

        return DB::connection(config('queue.failed.database'))
            ->table((string) config('queue.failed.table', 'failed_jobs'))
            ->groupBy('queue')
            ->selectRaw('queue, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'queue');
    }

    private function jobName(string $payload): string
    {
        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'unknown';
        }

        if (! is_array($decoded)) {
            return 'unknown';
        }

        $name = $decoded['displayName'] ?? $decoded['job'] ?? 'unknown';

        return is_string($name) ? class_basename($name) : 'unknown';
    }

    private function nullableTimestamp(mixed $timestamp): ?string
    {
        return $timestamp === null ? null : $this->timestamp($timestamp);
    }

    private function timestamp(mixed $timestamp): string
    {
        return CarbonImmutable::createFromTimestampUTC((int) $timestamp)->toIso8601String();
    }
}
