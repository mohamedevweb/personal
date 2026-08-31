<?php

namespace App\Console\Commands;

use App\Services\QueueStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;

class QueueStatus extends Command
{
    protected $signature = 'personal:queue-status
                            {--details : List the jobs currently in the queue}
                            {--limit=25 : Maximum number of jobs to list, from 1 to 100}';

    protected $description = 'Display a read-only snapshot of database queue activity';

    public function handle(QueueStatusService $status): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);

        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 100.');

            return self::INVALID;
        }

        try {
            $snapshot = $status->snapshot($limit);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($snapshot['queues'] === []) {
            $this->info('The queue is empty and no failed jobs are recorded.');
        } else {
            $this->table(
                ['Queue', 'Ready', 'Delayed', 'Reserved', 'Failed', 'Oldest pending (UTC)'],
                array_map(fn (array $queue): array => [
                    $queue['queue'],
                    $queue['ready'],
                    $queue['delayed'],
                    $queue['reserved'],
                    $queue['failed'],
                    $this->displayTimestamp($queue['oldest_pending_at']),
                ], $snapshot['queues']),
            );
        }

        if ($this->option('details') && $snapshot['jobs'] !== []) {
            $this->newLine();
            $this->line("Current jobs, limited to {$limit}:");
            $this->table(
                ['ID', 'Queue', 'State', 'Job', 'Attempts', 'Created (UTC)'],
                array_map(fn (array $job): array => [
                    $job['id'],
                    $job['queue'],
                    $job['state'],
                    $job['job'],
                    $job['attempts'],
                    $this->displayTimestamp($job['created_at']),
                ], $snapshot['jobs']),
            );
        }

        return self::SUCCESS;
    }

    private function displayTimestamp(?string $timestamp): string
    {
        return $timestamp === null
            ? '-'
            : CarbonImmutable::parse($timestamp)->utc()->format('Y-m-d H:i:s');
    }
}
