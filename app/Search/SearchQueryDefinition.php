<?php

namespace App\Search;

use Nexus\Search\Application\UseCase\SearchAcrossProviders;

final readonly class SearchQueryDefinition
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $providerAliases
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
        public array $providerAliases = [],
        public bool $includeRawData = false,
    ) {}

    public static function fromCoreItem(object $item): self
    {
        return new self(
            id: (string) $item->id,
            label: (string) $item->label,
            query: (string) $item->query,
            maxResults: (int) $item->maxResults,
            yearFrom: $item->yearFrom,
            yearTo: $item->yearTo,
            includeTitleAbstract: $item->includeTitleAbstract,
            excludeTitleAbstract: $item->excludeTitleAbstract,
            metadata: $item->metadata,
            providerAliases: $item->providerAliases,
            includeRawData: (bool) ($item->includeRawData ?? false),
        );
    }

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
            providerAliases: self::providerAliases($rawSearch['providers'] ?? []),
            includeRawData: self::boolValue($rawSearch['include_raw_data'] ?? false),
        );
    }

    public function toCoreCommand(string $projectId): SearchAcrossProviders
    {
        $arguments = [
            'query' => $this->query,
            'projectId' => $projectId,
            'maxResults' => $this->maxResults,
            'yearFrom' => $this->yearFrom,
            'yearTo' => $this->yearTo,
        ];

        if (self::coreCommandAcceptsProviderAliases()) {
            $arguments['providerAliases'] = $this->providerAliases;
        }

        if (self::coreCommandAcceptsIncludeRawData()) {
            $arguments['includeRawData'] = $this->includeRawData;
        }

        return new SearchAcrossProviders(...$arguments);
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

        if ($this->providerAliases !== []) {
            $metadata['providers'] = $this->providerAliases;
        }

        if ($this->includeRawData) {
            $metadata['include_raw_data'] = true;
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

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function providerAliases(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $aliases = is_array($value) ? $value : explode(',', (string) $value);
        $normalized = [];

        foreach ($aliases as $alias) {
            if (! is_scalar($alias)) {
                continue;
            }

            $alias = strtolower(trim((string) $alias));

            if ($alias !== '') {
                $normalized[$alias] = $alias;
            }
        }

        return array_values($normalized);
    }

    private static function coreCommandAcceptsProviderAliases(): bool
    {
        $constructor = new \ReflectionMethod(SearchAcrossProviders::class, '__construct');

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() === 'providerAliases') {
                return true;
            }
        }

        return false;
    }

    private static function coreCommandAcceptsIncludeRawData(): bool
    {
        $constructor = new \ReflectionMethod(SearchAcrossProviders::class, '__construct');

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() === 'includeRawData') {
                return true;
            }
        }

        return false;
    }
}
