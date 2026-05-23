<?php

namespace App\Search;

use Nexus\Search\Application\Dto\ScholarlyWorkDto;
use Nexus\Shared\Domain\ScholarlyWork;

final class SearchResultSerializer
{
    /**
     * @param  ScholarlyWork[]  $works
     * @return list<array<string, mixed>>
     */
    public function forQuery(array $works, SearchQueryDefinition $query): array
    {
        return array_map(
            fn (ScholarlyWork $work): array => $this->serializeWork($work, $query),
            $works
        );
    }

    /**
     * @param  ScholarlyWork[]  $works
     * @param  array<string, list<array<string, mixed>>>  $globalMatches
     * @return list<array<string, mixed>>
     */
    public function forGlobal(array $works, array $globalMatches): array
    {
        return array_map(function (ScholarlyWork $work) use ($globalMatches): array {
            $matches = $globalMatches[$this->workKey($work)] ?? [];
            $firstMatch = $matches[0] ?? null;

            $payload = $this->serializeWork($work);
            $payload['query_id'] = $firstMatch['id'] ?? null;
            $payload['query_metadata'] = $firstMatch['metadata'] ?? [];
            $payload['query_matches'] = $matches;

            return $payload;
        }, $works);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeWork(ScholarlyWork $work, ?SearchQueryDefinition $query = null): array
    {
        $payload = ScholarlyWorkDto::fromDomain($work);

        if ($query !== null) {
            $payload['query_id'] = $query->id;
            $payload['query_metadata'] = $query->metadataForRun();
        }

        return $payload;
    }

    public function workKey(ScholarlyWork $work): string
    {
        if ($work->primaryId() !== null) {
            return $work->primaryId()->toString();
        }

        return hash('sha256', implode('|', [
            mb_strtolower(trim($work->title())),
            (string) ($work->year() ?? ''),
            $work->sourceProvider(),
        ]));
    }
}
