<?php

namespace App\Console\Commands;

use App\Search\SearchConsoleRenderer;
use App\Search\SearchPlanLoader;
use App\Search\SearchRunService;
use App\Search\SearchSelection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler;

use function Laravel\Prompts\error;
use function Laravel\Prompts\warning;

class NexusSearch extends Command
{
    protected $signature = 'nexus:search
        {--id= : Run a specific query ID}
        {--all : Run all configured queries}
        {--file= : Query YAML file path, relative to resources/ or the app root}
        {--project= : Override the project ID passed to nexus-scholar/core}';

    protected $description = 'Run scholarly search queries through nexus-scholar/core and save JSON run files.';

    public function handle(
        SearchAcrossProvidersHandler $handler,
        SearchPlanLoader $plans,
        SearchRunService $runs,
        SearchConsoleRenderer $renderer,
    ): int {
        try {
            $selection = SearchSelection::fromOptions($this->option('id'), (bool) $this->option('all'));
            $plan = $plans->load($this->resolveQueriesPath());
        } catch (\Throwable $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        if ($plan->isEmpty()) {
            warning("No queries found in {$plan->sourcePath}");

            return self::SUCCESS;
        }

        try {
            $selected = $plan->select($selection);
        } catch (\Throwable $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        $projectId = (string) ($this->option('project') ?: $plan->projectId);
        $renderer->renderStart($this, $plan, count($selected), $projectId);

        try {
            $report = $runs->run(
                plan: $plan,
                selection: $selection,
                executor: $this->searchExecutor($handler),
                projectIdOverride: $projectId,
                onQueryCompleted: fn ($queryRun, $current, $total) => $renderer->renderQueryRun(
                    $this,
                    $queryRun,
                    $current,
                    $total
                ),
            );
        } catch (\Throwable $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        $renderer->renderFinished($this, $report);

        return self::SUCCESS;
    }

    private function searchExecutor(SearchAcrossProvidersHandler $fallback): object
    {
        $executorPort = 'Nexus\\Search\\Application\\Port\\SearchExecutorPort';

        return interface_exists($executorPort) && app()->bound($executorPort)
            ? app($executorPort)
            : $fallback;
    }

    private function resolveQueriesPath(): string
    {
        $file = $this->option('file');
        if (is_string($file) && trim($file) !== '') {
            return $this->normalizeInputPath($file);
        }

        $configured = config('nexus.search.queries_path', 'queries/thesis-queries.yml');

        return resource_path($configured);
    }

    private function normalizeInputPath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return $path;
        }

        $basePath = base_path($path);
        if (File::exists($basePath)) {
            return $basePath;
        }

        return resource_path($path);
    }
}
