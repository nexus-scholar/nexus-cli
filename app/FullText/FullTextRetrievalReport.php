<?php

namespace App\FullText;

final readonly class FullTextRetrievalReport
{
    /**
     * @param  list<array<string, mixed>>  $manifest
     */
    public function __construct(
        public string $runId,
        public string $destination,
        public string $manifestPath,
        public array $manifest,
    ) {}

    public function total(): int
    {
        return count($this->manifest);
    }

    public function countStatus(string $status): int
    {
        return count(array_filter(
            $this->manifest,
            static fn (array $entry): bool => ($entry['status'] ?? null) === $status,
        ));
    }
}
