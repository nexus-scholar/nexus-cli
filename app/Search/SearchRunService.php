<?php

namespace App\Search;

use Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\ScholarlyWork;

final class SearchRunService
{
    public function __construct(
        private readonly SearchResultSerializer $serializer,
        private readonly SearchRunWriter $writer,
    ) {}

    /**
     * @param  callable(SearchQueryRun, int, int): void|null  $onQueryCompleted
     */
    public function run(
        SearchPlan $plan,
        SearchSelection $selection,
        SearchAcrossProvidersHandler $handler,
        ?string $projectIdOverride = null,
        ?string $timestamp = null,
        ?callable $onQueryCompleted = null,
    ): SearchRunReport {
        $queries = $plan->select($selection);
        $projectId = $projectIdOverride ?: $plan->projectId;
        $timestamp ??= now()->format('Ymd_His');

        $globalCorpus = CorpusSlice::empty();
        $globalMatches = [];
        $queryRuns = [];

        foreach ($queries as $index => $query) {
            $coreCommand = $query->toCoreCommand($projectId);

            $startedAt = microtime(true);
            $result = $handler->handle($coreCommand);
            $elapsedMs = $result->durationMs > 0
                ? $result->durationMs
                : (int) round((microtime(true) - $startedAt) * 1000);

            $runFile = $this->writer->writeRun(
                prefix: $query->id,
                timestamp: $timestamp,
                payload: $this->serializer->forQuery($result->corpus->all(), $query),
            );

            $globalCorpus = $globalCorpus->merge($result->corpus);
            $this->rememberQueryMatches($globalMatches, $result->corpus->all(), $query);

            $queryRun = new SearchQueryRun(
                query: $query,
                coreQueryId: $coreCommand->query->id,
                result: $result,
                elapsedMs: $elapsedMs,
                file: $runFile,
            );
            $queryRuns[] = $queryRun;

            if ($onQueryCompleted !== null) {
                $onQueryCompleted($queryRun, $index + 1, count($queries));
            }
        }

        if ($selection->all) {
            $latestFile = $this->writer->writeRun(
                prefix: 'all',
                timestamp: $timestamp,
                payload: $this->serializer->forGlobal($globalCorpus->all(), $globalMatches),
            );
            $globalFile = $latestFile;
        } else {
            $latestFile = $queryRuns[0]->file;
            $globalFile = null;
        }

        $this->writer->writeLatestPointer($latestFile);

        return new SearchRunReport(
            plan: $plan,
            selection: $selection,
            projectId: $projectId,
            queryRuns: $queryRuns,
            globalFile: $globalFile,
            latestFile: $latestFile,
        );
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $globalMatches
     * @param  ScholarlyWork[]  $works
     */
    private function rememberQueryMatches(array &$globalMatches, array $works, SearchQueryDefinition $query): void
    {
        $match = [
            'id' => $query->id,
            'label' => $query->label,
            'metadata' => $query->metadataForRun(),
        ];

        foreach ($works as $work) {
            $key = $this->serializer->workKey($work);

            $globalMatches[$key] ??= [];
            if (! collect($globalMatches[$key])->contains(fn (array $existing): bool => $existing['id'] === $match['id'])) {
                $globalMatches[$key][] = $match;
            }
        }
    }
}
