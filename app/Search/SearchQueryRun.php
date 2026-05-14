<?php

namespace App\Search;

use Nexus\Search\Application\Aggregator\AggregatedResult;

final readonly class SearchQueryRun
{
    public function __construct(
        public SearchQueryDefinition $query,
        public string $coreQueryId,
        public AggregatedResult $result,
        public int $elapsedMs,
        public SearchRunFile $file,
    ) {}
}
