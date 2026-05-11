<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\error;
use function Laravel\Prompts\warning;

class NexusRunStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexus:run-stats {run? : path to run JSON, defaults to latest}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show quick stats for a run JSON (total, per provider, per query).';

    public function handle(): int
    {
        $runFile = $this->resolveRunFile();
        if ($runFile === null) {
            return self::FAILURE;
        }

        $runDataRaw = $this->safeReadFile($runFile);
        if ($runDataRaw === null) {
            return self::FAILURE;
        }

        $runData = json_decode($runDataRaw, true);
        if (!is_array($runData)) {
            error("Run file is not valid JSON: {$runFile}");
            return self::FAILURE;
        }

        if ($runData === []) {
            warning("Run file is empty: {$runFile}");
            return self::SUCCESS;
        }

        $providerCounts = [];
        $queryCounts = [];

        foreach ($runData as $index => $work) {
            if (!is_array($work)) {
                error("Run file has invalid entry at index {$index} (not an object).");
                return self::FAILURE;
            }

            $provider = $work['sourceProvider'] ?? $work['source_provider'] ?? 'unknown';
            $providerCounts[$provider] = ($providerCounts[$provider] ?? 0) + 1;

            $queryId = $this->resolveQueryId($work) ?? 'unknown';
            $queryCounts[$queryId] = ($queryCounts[$queryId] ?? 0) + 1;
        }

        $this->line('Run file: ' . $this->toRelativePath($runFile));
        $this->line('Total works: ' . count($runData));

        $this->table(
            ['Provider', 'Count'],
            $this->formatCounts($providerCounts)
        );

        if (count($queryCounts) > 1 || !isset($queryCounts['unknown'])) {
            $this->table(
                ['Query ID', 'Count'],
                $this->formatCounts($queryCounts)
            );
        } else {
            $this->line('Query stats: no query_id metadata found in this run.');
        }

        return self::SUCCESS;
    }

    private function resolveRunFile(): ?string
    {
        $runArg = $this->argument('run');
        if ($runArg) {
            $runPath = $this->normalizePath($runArg);
            if (!File::exists($runPath)) {
                error("Run file not found: {$runPath}");
                return null;
            }

            return $runPath;
        }

        $latestPointer = storage_path('runs/latest.json');
        if (!File::exists($latestPointer)) {
            error('latest.json pointer not found. Run nexus:search or pass a run file path.');
            return null;
        }

        $latest = json_decode(File::get($latestPointer), true);
        $latestFile = $latest['file'] ?? null;
        if (!is_string($latestFile) || $latestFile === '') {
            error('latest.json pointer is invalid.');
            return null;
        }

        $resolved = base_path($latestFile);
        if (!File::exists($resolved)) {
            error("Run file referenced by latest.json not found: {$resolved}");
            return null;
        }

        return $resolved;
    }

    private function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    private function safeReadFile(string $path): ?string
    {
        if (!File::exists($path)) {
            error("File not found: {$path}");
            return null;
        }

        try {
            return File::get($path);
        } catch (\Throwable $e) {
            error("Failed to read file: {$path}. {$e->getMessage()}");
            return null;
        }
    }

    private function toRelativePath(string $path): string
    {
        $base = base_path();
        if (Str::startsWith($path, $base)) {
            return ltrim(Str::after($path, $base), '\\\\/');
        }

        return $path;
    }

    private function resolveQueryId(array $work): ?string
    {
        if (isset($work['query_id']) && is_string($work['query_id'])) {
            return $work['query_id'];
        }

        $meta = $work['query_metadata'] ?? null;
        if (is_array($meta) && isset($meta['query_id']) && is_string($meta['query_id'])) {
            return $meta['query_id'];
        }

        return null;
    }

    private function formatCounts(array $counts): array
    {
        arsort($counts);
        $rows = [];
        foreach ($counts as $key => $count) {
            $rows[] = [$key, $count];
        }

        return $rows;
    }
}

