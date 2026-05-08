<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\File;
use Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;
use Nexus\Search\Application\Dto\ScholarlyWorkDto;
use Nexus\Search\Domain\ScholarlyWork;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;

class NexusSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexus:search {--id= : run specific query} {--all : run all queries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scholarly search queries and save results as JSON runs.';

    /**
     * Execute the console command.
     */
    public function handle(SearchAcrossProvidersHandler $handler)
    {
        $queriesPath = resource_path('queries/thesis-queries.yml');

        if (!File::exists($queriesPath)) {
            error("Queries file not found at: {$queriesPath}");
            return self::FAILURE;
        }

        $yaml = Yaml::parseFile($queriesPath);
        $searches = $yaml['searches'] ?? [];

        if (empty($searches)) {
            warning("No queries found in {$queriesPath}");
            return self::SUCCESS;
        }

        $idOpt = $this->option('id');
        $allOpt = $this->option('all');

        if (!$idOpt && !$allOpt) {
            error("You must specify either --id=QUERY_ID or --all.");
            return self::FAILURE;
        }

        if ($idOpt) {
            $searches = array_filter($searches, fn($q) => $q['id'] === $idOpt);
            if (empty($searches)) {
                error("Query ID '{$idOpt}' not found in thesis-queries.yml");
                return self::FAILURE;
            }
        }

        // Initialize runs directory
        $runsDir = storage_path('runs');
        if (!File::exists($runsDir)) {
            File::makeDirectory($runsDir, 0755, true);
        }

        $timestamp = now()->format('Ymd_His');
        $globalCorpusData = [];

        foreach ($searches as $search) {
            info("Executing search: {$search['id']} ({$search['label']})");
            $this->line("Query: {$search['query']}");

            $command = new SearchAcrossProviders(
                query: (string) $search['query'],
                projectId: 'default-project',
                maxResults: $search['limit'] ?? 50,
                yearFrom: $search['year_from'] ?? null
            );

            $result = $handler->handle($command);

            $this->line(sprintf('  Raw: <comment>%d</comment>  →  Unique: <comment>%d</comment>', $result->totalRaw, $result->corpus->count()));

            $runData = [];
            foreach ($result->corpus->all() as $work) {
                $workData = $this->mapWorkToArray($work);
                $workData['query_id'] = $search['id'] ?? null;
                $workData['query_metadata'] = $search['metadata'] ?? [];
                $runData[] = $workData;
                $primaryKey = $work->primaryId()?->toString() ?? uniqid('work_');
                $globalCorpusData[$primaryKey] = $workData;
            }

            $runFile = "{$runsDir}/{$search['id']}_{$timestamp}.json";
            File::put($runFile, json_encode(array_values($runData), JSON_PRETTY_PRINT));
            $this->line("  Saved to: {$runFile}");
        }

        if ($allOpt) {
            $globalFile = "{$runsDir}/all_{$timestamp}.json";
            File::put($globalFile, json_encode(array_values($globalCorpusData), JSON_PRETTY_PRINT));
            $this->line("  Saved global deduped master to: {$globalFile}");

            $latestPointer = "{$runsDir}/latest.json";
            $latestData = [
                'file' => "storage/runs/all_{$timestamp}.json",
                'run_at' => now()->toIso8601String()
            ];
            File::put($latestPointer, json_encode($latestData, JSON_PRETTY_PRINT));
            info("Updated storage/runs/latest.json pointer.");
        } elseif ($idOpt) {
            // For a single run, also update latest pointer to this file
            $latestPointer = "{$runsDir}/latest.json";
            $latestData = [
                'file' => "storage/runs/{$idOpt}_{$timestamp}.json",
                'run_at' => now()->toIso8601String()
            ];
            File::put($latestPointer, json_encode($latestData, JSON_PRETTY_PRINT));
            info("Updated storage/runs/latest.json pointer.");
        }

        return self::SUCCESS;
    }

    private function mapWorkToArray(ScholarlyWork $work): array
    {
        return ScholarlyWorkDto::fromDomain($work);
    }
}
