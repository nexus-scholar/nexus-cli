<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Deduplication\Application\LockCorpus;
use Nexus\Deduplication\Application\LockCorpusHandler;
use Nexus\Shared\Port\CorpusSnapshotRepositoryPort;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class NexusCorpusLock extends Command
{
    protected $signature = 'nexus:corpus-lock
        {--project= : project ID to lock}
        {--actor= : actor ID recorded in lock audit}
        {--reason= : human-readable reason for the lock}
        {--metadata=* : lock metadata as key=value pairs}';

    protected $description = 'Lock a Nexus Scholar project corpus and create an immutable corpus snapshot.';

    public function handle(LockCorpusHandler $handler, CorpusSnapshotRepositoryPort $snapshots): int
    {
        $projectId = $this->stringOption('project');

        if ($projectId === null) {
            error('Provide --project.');

            return self::FAILURE;
        }

        try {
            $handler->handle(new LockCorpus(
                projectId: $projectId,
                actorId: $this->stringOption('actor'),
                reason: $this->stringOption('reason'),
                metadata: $this->metadataOption(),
            ));

            $snapshot = $snapshots->latestForProject($projectId);
        } catch (\Throwable $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        info('Corpus locked.');
        $this->line('Project: '.$projectId);
        $this->line('Snapshot: '.($snapshot?->id ?? 'none'));
        $this->line('Snapshot works: '.($snapshot?->workCount ?? 0));

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataOption(): array
    {
        $values = $this->option('metadata');
        if (! is_array($values)) {
            return [];
        }

        $metadata = [];
        foreach ($values as $value) {
            if (! is_string($value) || ! str_contains($value, '=')) {
                continue;
            }

            [$key, $raw] = explode('=', $value, 2);
            $key = trim($key);

            if ($key !== '') {
                $metadata[$key] = trim($raw);
            }
        }

        return $metadata;
    }
}
