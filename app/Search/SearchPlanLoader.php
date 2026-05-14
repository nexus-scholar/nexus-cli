<?php

namespace App\Search;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

final class SearchPlanLoader
{
    public function load(string $path): SearchPlan
    {
        if (! File::exists($path)) {
            throw new \RuntimeException("Queries file not found at: {$path}");
        }

        $yaml = Yaml::parseFile($path);
        if (! is_array($yaml)) {
            throw new \RuntimeException("Queries file is not valid YAML: {$path}");
        }

        $rawQueries = $yaml['searches'] ?? $yaml['queries'] ?? [];
        if (! is_array($rawQueries)) {
            throw new \RuntimeException("Queries file must contain a 'searches' or 'queries' list.");
        }

        $queries = [];
        foreach (array_values($rawQueries) as $index => $rawQuery) {
            if (! is_array($rawQuery)) {
                throw new \RuntimeException("Query entry {$index} must be a mapping.");
            }

            $queries[] = SearchQueryDefinition::fromArray($rawQuery, $index);
        }

        return new SearchPlan(
            sourcePath: $path,
            projectId: (string) ($yaml['project'] ?? 'default-project'),
            queries: $queries,
        );
    }
}
