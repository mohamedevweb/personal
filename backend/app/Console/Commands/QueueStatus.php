<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;

class QueueStatus extends Command
{
    protected $signature = 'personal:queue-status
                            {--details : List the jobs currently in the queue}
                            {--limit=25 : Maximum number of jobs to list, from 1 to 100}';

    protected $description = 'Display a read-only snapshot of database queue activity';

    public function handle(): int
    {
        $connectionName = (string) config('queue.default');
        $queueConfig = config("queue.connections.{$connectionName}");

        if (! is_array($queueConfig) || ($queueConfig['driver'] ?? null) !== 'database') {
            $this->error("The [{$connectionName}] queue connection is not database-backed.");

            return self::FAILURE;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);

        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 100.');

            return self::INVALID;
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

        if ($queues->isEmpty()) {
            $this->info('The queue is empty and no failed jobs are recorded.');
        } else {
            $this->table(
                ['Queue', 'Ready', 'Delayed', 'Reserved', 'Failed', 'Oldest pending (UTC)'],
                $queues->map(fn (string $queue): array => [
                    $queue,
                    (int) $ready->get($queue, 0),
                    (int) $delayed->get($queue, 0),
                    (int) $reserved->get($queue, 0),
                    (int) $failed->get($queue, 0),
                    $this->timestamp($oldestPending->get($queue)),
                ])->all(),
            );
        }

        if ($this->option('details')) {
            $this->displayDetails($connection, $jobsTable, $now, $limit);
        }

        return self::SUCCESS;
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

    private function displayDetails(ConnectionInterface $connection, string $table, int $now, int $limit): void
    {
        $jobs = $connection->table($table)
            ->orderByRaw('reserved_at IS NULL')
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at']);

        if ($jobs->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line("Current jobs, limited to {$limit}:");
        $this->table(
            ['ID', 'Queue', 'State', 'Job', 'Attempts', 'Created (UTC)'],
            $jobs->map(fn (object $job): array => [
                $job->id,
                $job->queue,
                $job->reserved_at !== null ? 'reserved' : ((int) $job->available_at > $now ? 'delayed' : 'ready'),
                $this->jobName((string) $job->payload),
                $job->attempts,
                $this->timestamp($job->created_at),
            ])->all(),
        );
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

    private function timestamp(mixed $timestamp): string
    {
        if ($timestamp === null) {
            return '-';
        }

        return CarbonImmutable::createFromTimestampUTC((int) $timestamp)->format('Y-m-d H:i:s');
    }
}
