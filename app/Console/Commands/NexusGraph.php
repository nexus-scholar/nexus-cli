<?php

namespace App\Console\Commands;

use App\Citation\CitationGraphRunAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\Shared\ValueObject\WorkId;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class NexusGraph extends Command
{
    protected $signature = 'nexus:graph
        {run? : path to run JSON, defaults to latest}
        {--type= : graph type: citation, co_citation, or bibliographic_coupling}
        {--project= : project id for the generated graph}
        {--source= : source work id for shortest path, e.g. doi:10.1000/a}
        {--target= : target work id for shortest path, e.g. doi:10.1000/b}
        {--output= : output JSON path, defaults to storage/graphs/runid_type.json}
        {--dry-run : show graph stats without writing JSON}';

    protected $description = 'Build and analyze a citation graph from a search run JSON using nexus-scholar/core.';

    public function handle(CitationGraphRunAnalyzer $analyzer): int
    {
        $runFile = $this->resolveRunFile();
        if ($runFile === null) {
            return self::FAILURE;
        }

        $runData = $this->readRunData($runFile);
        if ($runData === null) {
            return self::FAILURE;
        }

        $type = $this->resolveType();
        if ($type === null) {
            return self::FAILURE;
        }

        [$source, $target] = $this->resolvePathEndpoints();
        if ($source === false || $target === false) {
            return self::FAILURE;
        }

        if (($source === null) !== ($target === null)) {
            error('Both --source and --target are required when computing a shortest path.');
            return self::FAILURE;
        }

        try {
            $analysis = $analyzer->analyze(
                runData: $runData,
                projectId: $this->projectId(),
                type: $type,
                pathSource: $source,
                pathTarget: $target,
            );
        } catch (\Throwable $exception) {
            error($exception->getMessage());
            return self::FAILURE;
        }

        $relativeRunFile = $this->toRelativePath($runFile);
        $this->line('Run file: ' . $relativeRunFile);
        $this->line('Graph type: ' . $type->value);
        $this->line('Works: ' . count($analysis->works));
        $this->line('Relationships extracted: ' . $analysis->relationshipCount());
        $this->line('Nodes: ' . $analysis->metrics->nodeCount);
        $this->line('Edges: ' . $analysis->metrics->edgeCount);
        $this->line('Density: ' . number_format($analysis->metrics->density, 6));

        if ($analysis->relationshipCount() === 0) {
            warning('No citation relationships found. Re-run search with include_raw_data: true or provide references in the run JSON.');
        }

        if ($source !== null && $target !== null) {
            if ($analysis->path === null) {
                warning('No shortest path found between the requested works.');
            } else {
                $this->line('Shortest path edges: ' . $analysis->path->edgeCount());
            }
        }

        if ($this->option('dry-run')) {
            info('Dry run: no graph file written.');
            return self::SUCCESS;
        }

        $outputFile = $this->resolveOutputFile($runFile, $type);
        $this->writeOutput($outputFile, $analysis->toPayload($relativeRunFile, now()->toIso8601String()));
        $this->line('Saved: ' . $this->toRelativePath($outputFile));

        return self::SUCCESS;
    }

    private function resolveRunFile(): ?string
    {
        $runArg = $this->argument('run');
        if ($runArg) {
            $runPath = $this->normalizePath((string) $runArg);
            if (! File::exists($runPath)) {
                error("Run file not found: {$runPath}");
                return null;
            }

            return $runPath;
        }

        $latestPointer = storage_path('runs/latest.json');
        if (! File::exists($latestPointer)) {
            error('latest.json pointer not found. Run nexus:search or pass a run file path.');
            return null;
        }

        $latest = json_decode(File::get($latestPointer), true);
        $latestFile = $latest['file'] ?? null;
        if (! is_string($latestFile) || $latestFile === '') {
            error('latest.json pointer is invalid.');
            return null;
        }

        $resolved = base_path($latestFile);
        if (! File::exists($resolved)) {
            error("Run file referenced by latest.json not found: {$resolved}");
            return null;
        }

        return $resolved;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function readRunData(string $runFile): ?array
    {
        $decoded = json_decode(File::get($runFile), true);
        if (! is_array($decoded)) {
            error("Run file is not valid JSON: {$runFile}");
            return null;
        }

        if ($decoded === []) {
            error("Run file is empty: {$runFile}");
            return null;
        }

        if (! array_is_list($decoded)) {
            error("Run file must contain a JSON list of works: {$runFile}");
            return null;
        }

        return $decoded;
    }

    private function resolveType(): ?CitationGraphType
    {
        $type = $this->option('type');
        $type = $type === null || $type === '' ? 'citation' : (string) $type;
        $type = strtolower(str_replace('-', '_', $type));

        return match ($type) {
            'citation' => CitationGraphType::CITATION,
            'co_citation' => CitationGraphType::CO_CITATION,
            'bibliographic_coupling' => CitationGraphType::BIBLIOGRAPHIC_COUPLING,
            default => tap(null, fn () => error('Invalid graph type. Use citation, co_citation, or bibliographic_coupling.')),
        };
    }

    /**
     * @return array{0: WorkId|null|false, 1: WorkId|null|false}
     */
    private function resolvePathEndpoints(): array
    {
        $source = $this->parseWorkIdOption('source');
        $target = $this->parseWorkIdOption('target');

        return [$source, $target];
    }

    private function parseWorkIdOption(string $option): WorkId|null|false
    {
        $value = $this->option($option);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return WorkId::fromString((string) $value);
        } catch (\InvalidArgumentException $exception) {
            error("Invalid --{$option} work id: {$exception->getMessage()}");
            return false;
        }
    }

    private function resolveOutputFile(string $runFile, CitationGraphType $type): string
    {
        $output = $this->option('output');
        if (is_string($output) && trim($output) !== '') {
            return $this->normalizePath($output);
        }

        $graphsDir = storage_path('graphs');
        $runId = pathinfo($runFile, PATHINFO_FILENAME);

        return "{$graphsDir}/{$runId}_{$type->value}.json";
    }

    private function projectId(): string
    {
        $project = $this->option('project');

        return is_string($project) && trim($project) !== ''
            ? trim($project)
            : 'default-project';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeOutput(string $outputFile, array $payload): void
    {
        $dir = dirname($outputFile);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put(
            $outputFile,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    private function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    private function toRelativePath(string $path): string
    {
        $base = base_path();
        if (Str::startsWith($path, $base)) {
            return ltrim(Str::after($path, $base), '\\/');
        }

        return $path;
    }
}
