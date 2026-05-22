<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Dissemination\Application\UseCase\ExportBibliography;
use Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler;
use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class NexusExportBibliography extends Command
{
    protected $signature = 'nexus:export-bibliography
        {--project= : project ID to export}
        {--format=csv : bibliography format: bibtex, ris, csv, json, or jsonl}
        {--output= : storage path, defaults to a timestamped exports path}
        {--query-ids= : optional comma-separated search query IDs}
        {--requested-by= : actor ID for export history}';

    protected $description = 'Export project bibliography through nexus-scholar/core and record lock/snapshot metadata.';

    public function handle(
        ProjectCorpusWorksPort $corpusWorks,
        WorkRepositoryPort $works,
        ExportBibliographyHandler $exports,
        CorpusLockPolicy $lockPolicy,
    ): int {
        $projectId = $this->stringOption('project');

        if ($projectId === null) {
            error('Provide --project.');

            return self::FAILURE;
        }

        try {
            $format = BibliographyFormat::from(strtolower($this->stringOption('format') ?? BibliographyFormat::CSV->value));
            $workIds = $corpusWorks->workIds($projectId, $this->csvOption('query-ids'));
            $corpus = $this->corpusFromWorkIds($workIds, $works);
            $path = $exports->handle(new ExportBibliography(
                corpus: $corpus,
                format: $format,
                filename: $this->outputPath($projectId, $format),
                projectId: $projectId,
                requestedBy: $this->stringOption('requested-by'),
            ));
            $metadata = $lockPolicy->exportMetadata($projectId);
        } catch (\Throwable $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        info('Bibliography exported.');
        $this->line('Project: '.$projectId);
        $this->line('Works: '.$corpus->count());
        $this->line('Path: '.$path);
        $this->line('Project locked: '.($metadata['project_locked'] ? 'yes' : 'no'));
        $this->line('Snapshot: '.($metadata['corpus_snapshot_id'] ?? 'none'));
        $this->line('Citable: '.($metadata['citable'] ? 'yes' : 'no'));
        $this->line('Final: '.($metadata['final'] ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $workIds
     */
    private function corpusFromWorkIds(array $workIds, WorkRepositoryPort $works): CorpusSlice
    {
        if ($workIds === []) {
            return CorpusSlice::empty();
        }

        $ids = array_map(
            static fn (string $workId): WorkId => new WorkId(WorkIdNamespace::INTERNAL, $workId),
            $workIds,
        );

        $found = $works->findManyByIds($ids);

        return $found === [] ? CorpusSlice::empty() : CorpusSlice::fromWorks(...array_values($found));
    }

    private function outputPath(string $projectId, BibliographyFormat $format): string
    {
        $output = $this->stringOption('output');
        if ($output !== null) {
            return $output;
        }

        $safeProject = preg_replace('/[^A-Za-z0-9._-]+/', '-', $projectId) ?: 'project';

        return sprintf('exports/%s-%s.%s', trim($safeProject, '-'), now()->format('Ymd_His'), $format->extension());
    }

    /**
     * @return list<string>
     */
    private function csvOption(string $name): array
    {
        $value = $this->stringOption($name);
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $value),
        )));
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
