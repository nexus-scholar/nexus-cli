<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Dissemination\Domain\FullTextFetchRecord;
use Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class NexusFullTextArtifacts extends Command
{
    protected $signature = 'nexus:full-text-artifacts
        {--work= : internal work ID or namespaced work ID}
        {--project= : project ID for project corpus artifacts}
        {--limit=25 : maximum records to return}
        {--json : emit JSON output}';

    protected $description = 'Read full-text fetch artifacts recorded by nexus-scholar/core.';

    public function handle(FullTextFetchReaderPort $fetches): int
    {
        $workId = $this->stringOption('work');
        $projectId = $this->stringOption('project');

        if (($workId === null) === ($projectId === null)) {
            error('Provide exactly one of --work or --project.');

            return self::FAILURE;
        }

        $records = $workId !== null
            ? $fetches->forWork($workId, $this->limitOption())
            : $fetches->forProject((string) $projectId, $this->limitOption());

        return $this->render($records);
    }

    /**
     * @param  list<FullTextFetchRecord>  $records
     */
    private function render(array $records): int
    {
        if ($this->option('json') === true) {
            $this->line(json_encode(array_map($this->fetchData(...), $records), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        if ($records === []) {
            info('No full-text fetch records found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Work', 'Source', 'Status', 'HTTP', 'File', 'Attempted'],
            array_map(fn (FullTextFetchRecord $record): array => [
                $record->workId,
                $record->sourceAlias,
                $record->status->value,
                (string) ($record->httpStatus ?? ''),
                $record->filePath ?? '',
                $record->attemptedAt->format(DATE_ATOM),
            ], $records),
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchData(FullTextFetchRecord $record): array
    {
        return [
            'id' => $record->id,
            'work_id' => $record->workId,
            'source_alias' => $record->sourceAlias,
            'source_url' => $record->sourceUrl,
            'status' => $record->status->value,
            'http_status' => $record->httpStatus,
            'file_path' => $record->filePath,
            'duration_ms' => $record->durationMs,
            'error_message' => $record->errorMessage,
            'attempted_at' => $record->attemptedAt->format(DATE_ATOM),
            'metadata' => $record->metadata,
            'created_at' => $record->createdAt?->format(DATE_ATOM),
            'updated_at' => $record->updatedAt?->format(DATE_ATOM),
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
