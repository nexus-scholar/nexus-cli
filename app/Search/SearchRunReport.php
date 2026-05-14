<?php

namespace App\Search;

final readonly class SearchRunReport
{
    /**
     * @param  list<SearchQueryRun>  $queryRuns
     */
    public function __construct(
        public SearchPlan $plan,
        public SearchSelection $selection,
        public string $projectId,
        public array $queryRuns,
        public ?SearchRunFile $globalFile,
        public SearchRunFile $latestFile,
    ) {}

    public function usedGlobalFile(): bool
    {
        return $this->globalFile !== null;
    }
}
