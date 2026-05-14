<?php

namespace App\Search;

final readonly class SearchPlan
{
    /**
     * @param  list<SearchQueryDefinition>  $queries
     */
    public function __construct(
        public string $sourcePath,
        public string $projectId,
        public array $queries,
    ) {}

    public function isEmpty(): bool
    {
        return $this->queries === [];
    }

    /**
     * @return list<SearchQueryDefinition>
     */
    public function select(SearchSelection $selection): array
    {
        if ($selection->all) {
            return $this->queries;
        }

        $selected = array_values(array_filter(
            $this->queries,
            fn (SearchQueryDefinition $query): bool => $query->id === $selection->id
        ));

        if ($selected === []) {
            throw new \InvalidArgumentException("Query ID '{$selection->id}' not found in ".basename($this->sourcePath));
        }

        return $selected;
    }
}
