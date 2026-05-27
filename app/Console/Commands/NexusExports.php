<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Dissemination\Domain\ExportHistoryRecord;
use Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class NexusExports extends Command
{
    protected $signature = 'nexus:exports
        {id? : export history ID to show}
        {--project= : project ID for latest exports}
        {--type= : export type filter}
        {--limit=10 : maximum records to return}
        {--json : emit JSON output}';

    protected $description = 'Read export history records from nexus-scholar/core.';

    public function handle(ExportHistoryReaderPort $exports): int
    {
        $id = $this->stringArgument('id');

        if ($id !== null) {
            $record = $exports->find($id);

            if ($record === null) {
                error('Export not found: '.$id);

                return self::FAILURE;
            }

            return $this->render([$record]);
        }

        return $this->render($exports->latest(
            projectId: $this->stringOption('project'),
            type: $this->stringOption('type'),
            limit: $this->limitOption(),
        ));
    }

    /**
     * @param  list<ExportHistoryRecord>  $records
     */
    private function render(array $records): int
    {
        if ($this->option('json') === true) {
            $this->line(json_encode(array_map($this->exportData(...), $records), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        if ($records === []) {
            info('No export history records found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Type', 'Format', 'Project', 'Filename', 'Size', 'Created'],
            array_map(fn (ExportHistoryRecord $record): array => [
                $record->id,
                $record->type->value,
                $record->format,
                $record->projectId ?? '',
                $record->filename,
                (string) $record->sizeBytes,
                $record->createdAt?->format(DATE_ATOM) ?? '',
            ], $records),
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportData(ExportHistoryRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => $record->type->value,
            'format' => $record->format,
            'filename' => $record->filename,
            'path' => $record->path,
            'mime_type' => $record->mimeType,
            'size_bytes' => $record->sizeBytes,
            'project_id' => $record->projectId,
            'corpus_slice_id' => $record->corpusSliceId,
            'citation_graph_id' => $record->citationGraphId,
            'requested_by' => $record->requestedBy,
            'metadata' => $record->metadata,
            'created_at' => $record->createdAt?->format(DATE_ATOM),
        ];
    }

    private function stringArgument(string $name): ?string
    {
        $value = $this->argument($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function limitOption(): int
    {
        $value = $this->option('limit');

        return is_numeric($value) ? max(1, (int) $value) : 10;
    }
}
