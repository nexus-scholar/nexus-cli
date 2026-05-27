<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Shared\Port\JobLifecycleReaderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class NexusJobs extends Command
{
    protected $signature = 'nexus:jobs
        {--run= : run ID to read}
        {--project= : project ID for latest job records}
        {--limit=25 : maximum records to return}
        {--status : show only the latest status for --run}
        {--json : emit JSON output}';

    protected $description = 'Read Nexus job lifecycle records from nexus-scholar/core.';

    public function handle(JobLifecycleReaderPort $jobs): int
    {
        $runId = $this->stringOption('run');
        $projectId = $this->stringOption('project');

        if (($runId === null) === ($projectId === null)) {
            error('Provide exactly one of --run or --project.');

            return self::FAILURE;
        }

        if ($this->option('status') === true) {
            if ($runId === null) {
                error('The --status option requires --run.');

                return self::FAILURE;
            }

            $status = $jobs->latestStatusForRun($runId);

            if ($this->option('json') === true) {
                $this->line(json_encode([
                    'run_id' => $runId,
                    'status' => $status?->value,
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $this->line('Run: '.$runId);
            $this->line('Status: '.($status?->value ?? 'unknown'));

            return self::SUCCESS;
        }

        $records = $runId !== null
            ? $jobs->forRun($runId, $this->limitOption())
            : $jobs->latestForProject((string) $projectId, $this->limitOption());

        return $this->render($records);
    }

    /**
     * @param  list<JobLifecycleRecord>  $records
     */
    private function render(array $records): int
    {
        if ($this->option('json') === true) {
            $this->line(json_encode(array_map($this->jobData(...), $records), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        if ($records === []) {
            info('No job lifecycle records found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Occurred', 'Run', 'Job', 'Status', 'Duration', 'Error'],
            array_map(fn (JobLifecycleRecord $record): array => [
                $record->occurredAt->format(DATE_ATOM),
                $record->runId,
                $record->jobName,
                $record->status->value,
                (string) $record->durationMs,
                $record->errorMessage ?? '',
            ], $records),
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function jobData(JobLifecycleRecord $record): array
    {
        return [
            'idempotency_key' => $record->idempotencyKey,
            'run_id' => $record->runId,
            'job_name' => $record->jobName,
            'job_class' => $record->jobClass,
            'status' => $record->status->value,
            'context' => $record->context,
            'summary' => $record->summary,
            'error_class' => $record->errorClass,
            'error_message' => $record->errorMessage,
            'duration_ms' => $record->durationMs,
            'occurred_at' => $record->occurredAt->format(DATE_ATOM),
        ];
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function limitOption(): int
    {
        $value = $this->option('limit');

        return is_numeric($value) ? max(1, (int) $value) : 25;
    }
}
