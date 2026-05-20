<?php

namespace App\Citation;

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphId;
use Nexus\CitationNetwork\Domain\NetworkMetrics;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;

final class InMemoryCitationGraphRepository implements CitationGraphRepositoryPort
{
    /**
     * @var array<string, CitationGraph>
     */
    private array $graphs = [];

    /**
     * @var array<string, NetworkMetrics>
     */
    private array $metrics = [];

    public function save(CitationGraph $graph): void
    {
        $this->graphs[$graph->id->toString()] = $graph;
    }

    public function findById(CitationGraphId $id): ?CitationGraph
    {
        return $this->graphs[$id->toString()] ?? null;
    }

    public function findByProjectId(string $projectId): array
    {
        return array_values(array_filter(
            $this->graphs,
            fn (CitationGraph $graph): bool => $graph->projectId === $projectId,
        ));
    }

    public function saveMetrics(CitationGraphId $id, NetworkMetrics $metrics): void
    {
        $this->metrics[$id->toString()] = $metrics;
    }

    public function delete(CitationGraphId $id): void
    {
        unset($this->graphs[$id->toString()], $this->metrics[$id->toString()]);
    }
}
