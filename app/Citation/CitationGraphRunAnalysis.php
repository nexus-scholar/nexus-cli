<?php

namespace App\Citation;

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationPath;
use Nexus\CitationNetwork\Domain\NetworkMetrics;
use Nexus\Shared\Domain\ScholarlyWork;

final readonly class CitationGraphRunAnalysis
{
    /**
     * @param  list<ScholarlyWork>  $works
     * @param  array<string, list<string>>  $referencesByWorkId
     * @param  array<string, list<string>>  $citingWorkIdsByCitedWorkId
     */
    public function __construct(
        public CitationGraph $graph,
        public NetworkMetrics $metrics,
        public array $works,
        public array $referencesByWorkId,
        public array $citingWorkIdsByCitedWorkId,
        public ?CitationPath $path = null,
    ) {}

    public function relationshipCount(): int
    {
        return array_sum(array_map('count', $this->referencesByWorkId))
            + array_sum(array_map('count', $this->citingWorkIdsByCitedWorkId));
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(string $runFile, string $generatedAt): array
    {
        return [
            'run_file' => $runFile,
            'generated_at' => $generatedAt,
            'graph_id' => $this->graph->id->toString(),
            'project_id' => $this->graph->projectId,
            'type' => $this->graph->type->value,
            'input' => [
                'works' => count($this->works),
                'relationships' => $this->relationshipCount(),
                'references' => array_sum(array_map('count', $this->referencesByWorkId)),
                'citing_relationships' => array_sum(array_map('count', $this->citingWorkIdsByCitedWorkId)),
            ],
            'graph' => [
                'nodes' => $this->nodes(),
                'edges' => $this->edges(),
            ],
            'metrics' => $this->metrics->toArray(),
            'path' => $this->path?->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nodes(): array
    {
        return array_map(function (ScholarlyWork $work): array {
            return [
                'id' => $work->primaryId()?->toString(),
                'title' => $work->title(),
                'year' => $work->year(),
                'source_provider' => $work->sourceProvider(),
                'ids' => array_map(
                    fn ($id): string => $id->toString(),
                    $work->ids()->all(),
                ),
            ];
        }, $this->graph->allWorks());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function edges(): array
    {
        return array_map(fn ($edge): array => [
            'citing' => $edge->citing->toString(),
            'cited' => $edge->cited->toString(),
            'weight' => $edge->weight,
        ], $this->graph->allEdges());
    }
}
