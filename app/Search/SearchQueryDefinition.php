<?php

namespace App\Search;

use Nexus\Search\Application\UseCase\SearchAcrossProviders;

final readonly class SearchQueryDefinition
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $query,
        public int $maxResults,
        public ?int $yearFrom,
        public ?int $yearTo,
        public ?string $includeTitleAbstract,
        public ?string $excludeTitleAbstract,
        public array $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $rawSearch
     */
    public static function fromArray(array $rawSearch, int $index): self
    {
        $id = trim((string) ($rawSearch['id'] ?? ''));
        $query = trim((string) ($rawSearch['query'] ?? $rawSearch['text'] ?? ''));

        if ($id === '') {
            throw new \RuntimeException("Query entry {$index} is missing an id.");
        }

        if ($query === '') {
            throw new \RuntimeException("Query '{$id}' is missing query text.");
        }

        $metadata = $rawSearch['metadata'] ?? [];
        if (! is_array($metadata)) {
            throw new \RuntimeException("Query '{$id}' metadata must be a mapping.");
        }

        return new self(
            id: $id,
            label: (string) ($rawSearch['label'] ?? $id),
            query: $query,
            maxResults: max(1, (int) ($rawSearch['limit'] ?? $rawSearch['max_results'] ?? 50)),
            yearFrom: self::nullableInt($rawSearch['year_from'] ?? $rawSearch['year_min'] ?? null),
            yearTo: self::nullableInt($rawSearch['year_to'] ?? $rawSearch['year_max'] ?? null),
            includeTitleAbstract: self::nullableString($rawSearch['include_title_abstract'] ?? null),
            excludeTitleAbstract: self::nullableString($rawSearch['exclude_title_abstract'] ?? null),
            metadata: $metadata,
        );
    }

    public function toCoreCommand(string $projectId): SearchAcrossProviders
    {
        return new SearchAcrossProviders(
            query: $this->query,
            projectId: $projectId,
            maxResults: $this->maxResults,
            yearFrom: $this->yearFrom,
            yearTo: $this->yearTo,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function metadataForRun(): array
    {
        $metadata = $this->metadata;
        $metadata['query_id'] = $this->id;
        $metadata['label'] = $this->label;
        $metadata['query'] = $this->query;
        $metadata['limit'] = $this->maxResults;

        if ($this->yearFrom !== null) {
            $metadata['year_from'] = $this->yearFrom;
        }

        if ($this->yearTo !== null) {
            $metadata['year_to'] = $this->yearTo;
        }

        if ($this->includeTitleAbstract !== null) {
            $metadata['include_title_abstract'] = $this->includeTitleAbstract;
        }

        if ($this->excludeTitleAbstract !== null) {
            $metadata['exclude_title_abstract'] = $this->excludeTitleAbstract;
        }

        return $metadata;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
