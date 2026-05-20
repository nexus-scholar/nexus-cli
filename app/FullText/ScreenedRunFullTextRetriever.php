<?php

namespace App\FullText;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Application\UseCase\RetrieveFullText;
use Nexus\Dissemination\Application\UseCase\RetrieveFullTextHandler;
use Nexus\Search\Application\Dto\ScholarlyWorkDto;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

final readonly class ScreenedRunFullTextRetriever
{
    /**
     * @var list<string>
     */
    private const SCHOLARLY_WORK_DTO_KEYS = [
        'ids',
        'title',
        'authors',
        'year',
        'venue',
        'abstract',
        'citedByCount',
        'isRetracted',
        'sourceProvider',
        'rawData',
    ];

    public function __construct(
        private RetrieveFullTextHandler $handler,
        private WorkRepositoryPort $works,
    ) {}

    public function retrieve(
        string $screenFile,
        ?string $destination = null,
        int $maxDownloadAttempts = 2,
        int $maxBytes = 50_000_000,
        int $failedAttemptCooldownSeconds = 3600,
    ): FullTextRetrievalReport {
        $screenData = $this->readJsonObject($screenFile, 'Screen file');
        $runFile = $this->runFileFromScreen($screenData);
        $runData = $this->readJsonList($runFile, 'Run file');
        $includedTitles = $this->includedTitles($screenData['decisions'] ?? []);

        $runId = pathinfo($runFile, PATHINFO_FILENAME);
        $destination = $this->safeDestination($destination ?: "full-text/{$runId}");

        $manifest = [];
        foreach ($runData as $index => $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '' || ! isset($includedTitles[$title])) {
                continue;
            }

            $work = $this->workFromPayload($payload, $index);
            if ($work->primaryId() !== null) {
                $this->works->save($work);
            }

            $result = $this->handler->handle(new RetrieveFullText(
                work: $work,
                destinationFolder: $destination,
                maxDownloadAttempts: $maxDownloadAttempts,
                maxBytes: $maxBytes,
                failedAttemptCooldownSeconds: $failedAttemptCooldownSeconds,
            ));

            $manifest[] = $this->manifestEntry($work, $result);
        }

        $disk = (string) config('nexus.dissemination.pdf_storage_disk', 'public');
        $manifestPath = "{$destination}/manifest.json";
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($manifestJson)) {
            throw new \RuntimeException('Failed to encode full-text retrieval manifest.');
        }

        Storage::disk($disk)->put($manifestPath, $manifestJson);

        return new FullTextRetrievalReport($runId, $destination, $manifestPath, $manifest);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonObject(string $path, string $label): array
    {
        $data = json_decode(File::get($path), true);

        if (! is_array($data) || array_is_list($data)) {
            throw new \RuntimeException("{$label} is not a valid JSON object: {$path}");
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readJsonList(string $path, string $label): array
    {
        $data = json_decode(File::get($path), true);

        if (! is_array($data) || ! array_is_list($data)) {
            throw new \RuntimeException("{$label} is not a valid JSON list: {$path}");
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $screenData
     */
    private function runFileFromScreen(array $screenData): string
    {
        $runFile = $screenData['run_file'] ?? null;
        if (! is_string($runFile) || trim($runFile) === '') {
            throw new \RuntimeException('Screen file does not include run_file path.');
        }

        $resolved = $this->normalizePath($runFile);
        if (! File::exists($resolved)) {
            throw new \RuntimeException("Run file not found: {$resolved}");
        }

        return $resolved;
    }

    /**
     * @return array<string, true>
     */
    private function includedTitles(mixed $decisions): array
    {
        if (! is_array($decisions)) {
            return [];
        }

        $titles = [];
        foreach ($decisions as $decision) {
            if (! is_array($decision)) {
                continue;
            }

            if (($decision['included'] ?? false) === true && isset($decision['title'])) {
                $titles[(string) $decision['title']] = true;
            }
        }

        return $titles;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function workFromPayload(array $payload, int $index): ScholarlyWork
    {
        if ($this->isScholarlyWorkDto($payload)) {
            return ScholarlyWorkDto::toDomain($payload);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException("Run file entry {$index} is missing title.");
        }

        return ScholarlyWork::reconstitute(
            ids: $this->idsFromPayload($payload['ids'] ?? []),
            title: $title,
            sourceProvider: (string) ($payload['sourceProvider'] ?? $payload['source_provider'] ?? 'run_file'),
            year: is_int($payload['year'] ?? null) ? $payload['year'] : null,
            authors: AuthorList::empty(),
            venue: $this->venueFromPayload($payload['venue'] ?? null),
            abstract: isset($payload['abstract']) && is_string($payload['abstract']) ? $payload['abstract'] : null,
            citedByCount: is_int($payload['citedByCount'] ?? null) ? $payload['citedByCount'] : null,
            isRetracted: (bool) ($payload['isRetracted'] ?? false),
            rawData: $this->rawDataFromPayload($payload),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isScholarlyWorkDto(array $payload): bool
    {
        foreach (self::SCHOLARLY_WORK_DTO_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                return false;
            }
        }

        return true;
    }

    private function idsFromPayload(mixed $ids): WorkIdSet
    {
        $set = WorkIdSet::empty();
        if (! is_array($ids)) {
            return $set;
        }

        foreach ($ids as $id) {
            if (! is_array($id)) {
                continue;
            }

            $namespace = WorkIdNamespace::tryFrom((string) ($id['ns'] ?? $id['namespace'] ?? ''));
            $value = trim((string) ($id['val'] ?? $id['value'] ?? ''));

            if ($namespace === null || $value === '') {
                continue;
            }

            $set = $set->add(new WorkId($namespace, $value));
        }

        return $set;
    }

    private function venueFromPayload(mixed $venue): ?Venue
    {
        if (! is_array($venue) || ! isset($venue['name']) || ! is_string($venue['name'])) {
            return null;
        }

        return new Venue(
            name: $venue['name'],
            issn: isset($venue['issn']) && is_string($venue['issn']) ? $venue['issn'] : null,
            type: isset($venue['type']) && is_string($venue['type']) ? $venue['type'] : 'journal',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function rawDataFromPayload(array $payload): ?array
    {
        foreach (['rawData', 'raw_data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        $raw = [];
        foreach (['direct_pdf_url', 'directPdfUrl', 'pdf_url', 'pdfUrl', 'full_text_pdf_url', 'fullTextPdfUrl'] as $key) {
            if (isset($payload[$key])) {
                $raw[$key] = $payload[$key];
            }
        }

        foreach (['best_oa_location', 'primary_location', 'locations', 'oa_locations', 'openAccessPdf'] as $key) {
            if (isset($payload[$key])) {
                $raw[$key] = $payload[$key];
            }
        }

        return $raw === [] ? null : $raw;
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestEntry(ScholarlyWork $work, FullTextResult $result): array
    {
        return array_filter([
            'title' => $work->title(),
            'primary_id' => $work->primaryId()?->toString(),
            'ids' => array_map(
                static fn (WorkId $id): string => $id->toString(),
                $work->ids()->all(),
            ),
            'status' => $result->status->value,
            'source_alias' => $result->sourceAlias,
            'artifact_path' => $result->filePath,
            'http_status' => $result->httpStatus,
            'error' => $result->errorMessage,
            'metadata' => $result->metadata === [] ? null : $result->metadata,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    private function safeDestination(string $destination): string
    {
        $segments = preg_split('#[\\\\/]+#', $destination) ?: [];
        $safe = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', $segment) ?? '';
            $clean = trim($clean, '._-');

            if ($clean !== '') {
                $safe[] = substr($clean, 0, 80);
            }
        }

        return $safe === [] ? 'full-text' : implode('/', $safe);
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return $path;
        }

        return base_path($path);
    }
}
