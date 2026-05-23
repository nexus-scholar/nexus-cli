<?php

namespace App\Citation;

use Nexus\CitationNetwork\Application\Builder\CitationGraphBuilder;
use Nexus\CitationNetwork\Application\UseCase\AnalyzeNetwork;
use Nexus\CitationNetwork\Application\UseCase\AnalyzeNetworkHandler;
use Nexus\CitationNetwork\Application\UseCase\BuildCitationGraph;
use Nexus\CitationNetwork\Application\UseCase\BuildCitationGraphHandler;
use Nexus\CitationNetwork\Application\UseCase\FindShortestCitationPath;
use Nexus\CitationNetwork\Application\UseCase\FindShortestCitationPathHandler;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\CitationNetwork\Infrastructure\Graph\MbsoftNetworkMetricsCalculator;
use Nexus\Search\Application\Dto\ScholarlyWorkDto;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;

final class CitationGraphRunAnalyzer
{
    /**
     * @param  list<array<string, mixed>>  $runData
     */
    public function analyze(
        array $runData,
        string $projectId,
        CitationGraphType $type,
        ?WorkId $pathSource = null,
        ?WorkId $pathTarget = null,
    ): CitationGraphRunAnalysis {
        [$works, $referencesByWorkId, $citingWorkIdsByCitedWorkId] = $this->extract($runData);

        $repository = new InMemoryCitationGraphRepository;
        $buildHandler = new BuildCitationGraphHandler(new CitationGraphBuilder, $repository);
        $algorithm = new MbsoftNetworkMetricsCalculator;

        $command = match ($type) {
            CitationGraphType::CITATION => BuildCitationGraph::directCitation(
                $projectId,
                $works,
                $referencesByWorkId,
            ),
            CitationGraphType::CO_CITATION => BuildCitationGraph::coCitation(
                $projectId,
                $works,
                $referencesByWorkId,
                $citingWorkIdsByCitedWorkId,
            ),
            CitationGraphType::BIBLIOGRAPHIC_COUPLING => BuildCitationGraph::bibliographicCoupling(
                $projectId,
                $works,
                $referencesByWorkId,
            ),
        };

        $graph = $buildHandler->handle($command);
        $metrics = (new AnalyzeNetworkHandler($repository, $algorithm))
            ->handle(new AnalyzeNetwork($graph->id, persistMetrics: false));

        $path = null;
        if ($pathSource !== null && $pathTarget !== null) {
            $path = (new FindShortestCitationPathHandler($repository, $algorithm))
                ->handle(new FindShortestCitationPath($graph->id, $pathSource, $pathTarget));
        }

        return new CitationGraphRunAnalysis(
            graph: $graph,
            metrics: $metrics,
            works: $works,
            referencesByWorkId: $referencesByWorkId,
            citingWorkIdsByCitedWorkId: $citingWorkIdsByCitedWorkId,
            path: $path,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $runData
     * @return array{0: list<ScholarlyWork>, 1: array<string, list<string>>, 2: array<string, list<string>>}
     */
    private function extract(array $runData): array
    {
        $works = [];
        $referencesByWorkId = [];
        $citingWorkIdsByCitedWorkId = [];

        foreach ($runData as $index => $payload) {
            if (! is_array($payload)) {
                throw new \RuntimeException("Run file entry {$index} must be an object.");
            }

            $work = $this->toWork($payload, $index);
            $works[] = $work;

            $primaryId = $work->primaryId();
            if ($primaryId === null) {
                continue;
            }

            $workId = $primaryId->toString();
            $references = $this->extractReferences($payload);
            $citingWorks = $this->extractCitingWorks($payload);

            if ($references !== []) {
                $referencesByWorkId[$workId] = $references;
            }

            if ($citingWorks !== []) {
                $citingWorkIdsByCitedWorkId[$workId] = $citingWorks;
            }
        }

        return [$works, $referencesByWorkId, $citingWorkIdsByCitedWorkId];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toWork(array $payload, int $index): ScholarlyWork
    {
        foreach (['ids', 'title', 'authors', 'year', 'venue', 'abstract', 'citedByCount', 'isRetracted', 'sourceProvider', 'rawData'] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new \RuntimeException("Run file entry {$index} is missing ScholarlyWork DTO key: {$key}.");
            }
        }

        return ScholarlyWorkDto::toDomain($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function extractReferences(array $payload): array
    {
        $raw = $this->rawData($payload);

        return $this->normalizeIdList([
            $payload['references'] ?? null,
            $payload['reference_ids'] ?? null,
            $payload['referenced_works'] ?? null,
            $payload['referencedWorks'] ?? null,
            $raw['references'] ?? null,
            $raw['referenceIds'] ?? null,
            $raw['referenced_works'] ?? null,
            $raw['referencedWorks'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function extractCitingWorks(array $payload): array
    {
        $raw = $this->rawData($payload);

        return $this->normalizeIdList([
            $payload['citations'] ?? null,
            $payload['citation_ids'] ?? null,
            $payload['cited_by'] ?? null,
            $payload['citing_works'] ?? null,
            $payload['citingWorkIds'] ?? null,
            $raw['citations'] ?? null,
            $raw['citationIds'] ?? null,
            $raw['cited_by'] ?? null,
            $raw['citing_works'] ?? null,
            $raw['citingWorkIds'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function rawData(array $payload): array
    {
        return isset($payload['rawData']) && is_array($payload['rawData'])
            ? $payload['rawData']
            : [];
    }

    /**
     * @return list<string>
     */
    private function normalizeIdList(mixed $value): array
    {
        $ids = [];
        $this->collectIds($value, $ids);

        return array_values($ids);
    }

    /**
     * @param  array<string, string>  $ids
     */
    private function collectIds(mixed $value, array &$ids, ?string $hint = null): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value) || is_int($value)) {
            foreach ($this->normalizeSingleId((string) $value, $hint) as $id) {
                $ids[$id] = $id;
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($this->idsFromObject($value) as $id) {
            $ids[$id] = $id;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, ['externalIds', 'external_ids', 'ids'], true) && is_array($item)) {
                foreach ($this->idsFromObject($item) as $id) {
                    $ids[$id] = $id;
                }

                continue;
            }

            if (is_int($key)) {
                $this->collectIds($item, $ids, $hint);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @return list<string>
     */
    private function idsFromObject(array $value): array
    {
        $ids = [];

        $knownKeys = [
            'doi' => 'doi',
            'DOI' => 'doi',
            'arxiv' => 'arxiv',
            'ArXiv' => 'arxiv',
            'pubmed' => 'pubmed',
            'PubMed' => 'pubmed',
            'pmid' => 'pubmed',
            'PMID' => 'pubmed',
            'openalex' => 'openalex',
            'openalex_id' => 'openalex',
            'paperId' => 's2',
            's2' => 's2',
            's2_id' => 's2',
            'id' => null,
        ];

        foreach ($knownKeys as $key => $hint) {
            if (array_key_exists($key, $value) && is_scalar($value[$key])) {
                foreach ($this->normalizeSingleId((string) $value[$key], $hint) as $id) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @return list<string>
     */
    private function normalizeSingleId(string $value, ?string $hint = null): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(doi|arxiv|openalex|s2|pubmed|ieee|doaj|internal):.+/i', $value) === 1) {
            return [strtolower($value)];
        }

        if (preg_match('#^https?://(dx\.)?doi\.org/(.+)$#i', $value, $matches) === 1) {
            return ['doi:'.strtolower($matches[2])];
        }

        if (preg_match('#^https?://openalex\.org/(W\d+)$#i', $value) === 1) {
            return ['openalex:'.strtolower($value)];
        }

        if ($hint !== null) {
            return [strtolower($hint.':'.$value)];
        }

        if (str_starts_with(strtolower($value), '10.')) {
            return ['doi:'.strtolower($value)];
        }

        if (preg_match('/^W\d+$/i', $value) === 1) {
            return [
                'openalex:'.strtolower($value),
                'openalex:https://openalex.org/'.strtolower($value),
            ];
        }

        return [];
    }
}
