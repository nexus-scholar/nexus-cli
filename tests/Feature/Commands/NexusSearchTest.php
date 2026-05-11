<?php

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Application\Dto\ScholarlyWorkDto;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    $this->queriesPath = resource_path('queries/thesis-queries-old-1.yml');
    $this->runsDir = storage_path('runs');
    $this->latestPointer = "{$this->runsDir}/latest.json";

    $this->hadQueriesFile = File::exists($this->queriesPath);
    $this->originalQueriesContent = $this->hadQueriesFile ? File::get($this->queriesPath) : null;

    $this->hadLatestPointer = File::exists($this->latestPointer);
    $this->originalLatestContent = $this->hadLatestPointer ? File::get($this->latestPointer) : null;

    $this->existingRunFiles = [];
    if (File::exists($this->runsDir)) {
        $this->existingRunFiles = array_map(
            fn ($file) => $file->getPathname(),
            File::files($this->runsDir)
        );
    }

    $this->createdProjectsTable = false;
    if (!Schema::hasTable('projects')) {
        Schema::create('projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->timestamp('locked_at')->nullable();
        });
        $this->createdProjectsTable = true;
    }
});

afterEach(function () {
    if ($this->hadQueriesFile) {
        File::put($this->queriesPath, $this->originalQueriesContent);
    } else {
        if (File::exists($this->queriesPath)) {
            File::delete($this->queriesPath);
        }
    }

    if ($this->hadLatestPointer) {
        File::put($this->latestPointer, $this->originalLatestContent);
    } else {
        if (File::exists($this->latestPointer)) {
            File::delete($this->latestPointer);
        }
    }

    if (File::exists($this->runsDir)) {
        $currentFiles = File::files($this->runsDir);
        foreach ($currentFiles as $file) {
            $path = $file->getPathname();
            if (!in_array($path, $this->existingRunFiles, true)) {
                File::delete($path);
            }
        }
    }

    if ($this->createdProjectsTable) {
        Schema::drop('projects');
    }

    Carbon::setTestNow();
});

function writeQueriesFile(string $path, array $searches): void
{
    $yaml = Yaml::dump(['searches' => $searches], 4, 2);
    File::put($path, $yaml);
}

function bindAggregator(AggregatedResult|callable $resultFactory): void
{
    app()->instance(SearchAggregatorPort::class, new class($resultFactory) implements SearchAggregatorPort {
        private $resultFactory;

        public function __construct($resultFactory)
        {
            $this->resultFactory = $resultFactory;
        }

        public function aggregate(SearchQuery $query): AggregatedResult
        {
            if (is_callable($this->resultFactory)) {
                return ($this->resultFactory)($query);
            }

            return $this->resultFactory;
        }
    });
}

function makeWork(
    string $title,
    string $provider,
    WorkId $primaryId,
    ?int $year = null,
    ?string $abstract = null
): ScholarlyWork {
    $authors = AuthorList::fromArray([
        new Author('Doe', 'Jane'),
        new Author('Smith', 'John'),
    ]);

    $ids = WorkIdSet::fromArray([$primaryId]);

    return ScholarlyWork::reconstitute(
        ids: $ids,
        title: $title,
        sourceProvider: $provider,
        year: $year,
        authors: $authors,
        abstract: $abstract,
        citedByCount: 12,
        isRetracted: false,
        rawData: null
    );
}

test('fails when queries file is missing', function () {
    if (File::exists($this->queriesPath)) {
        File::delete($this->queriesPath);
    }

    $this->artisan('nexus:search', ['--all' => true])
        ->assertExitCode(1);
});

test('returns success when queries list is empty', function () {
    writeQueriesFile($this->queriesPath, []);

    $this->artisan('nexus:search', ['--all' => true])
        ->assertExitCode(0);
});

test('fails when neither id nor all is provided', function () {
    writeQueriesFile($this->queriesPath, [
        ['id' => 'tomato', 'label' => 'Tomato', 'query' => 'tomato segmentation'],
    ]);

    $this->artisan('nexus:search')
        ->assertExitCode(1);
});

test('fails when id is not found', function () {
    writeQueriesFile($this->queriesPath, [
        ['id' => 'tomato', 'label' => 'Tomato', 'query' => 'tomato segmentation'],
    ]);

    $this->artisan('nexus:search', ['--id' => 'missing'])
        ->assertExitCode(1);
});

test('writes per-query run and latest pointer using dto payloads', function () {
    Carbon::setTestNow('2026-05-05 00:07:38');

    writeQueriesFile($this->queriesPath, [
        ['id' => 'tomato', 'label' => 'Tomato', 'query' => 'tomato segmentation', 'limit' => 10],
    ]);

    $work = makeWork(
        title: 'Paper A',
        provider: 'openalex',
        primaryId: new WorkId(WorkIdNamespace::DOI, '10.1234/abc'),
        year: 2024,
        abstract: 'Example abstract.'
    );

    $result = new AggregatedResult(
        corpus: CorpusSlice::fromWorks($work),
        providerStats: [],
        totalRaw: 1
    );

    bindAggregator($result);

    $this->artisan('nexus:search', ['--id' => 'tomato'])
        ->assertExitCode(0);

    $runFile = "{$this->runsDir}/tomato_20260505_000738.json";
    $latestPointer = "{$this->runsDir}/latest.json";

    expect(File::exists($runFile))->toBeTrue();
    expect(File::exists($latestPointer))->toBeTrue();

    $runPayload = json_decode(File::get($runFile), true);
    expect($runPayload)->toHaveCount(1);

    $expected = ScholarlyWorkDto::fromDomain($work);
    expect($runPayload[0])->toMatchArray($expected);
    expect($runPayload[0])->toHaveKeys(['query_id', 'query_metadata']);

    $latestPayload = json_decode(File::get($latestPointer), true);
    expect($latestPayload['file'])->toBe('storage/runs/tomato_20260505_000738.json');
    expect($latestPayload['run_at'])->toBe(Carbon::now()->toIso8601String());
});

test('writes global deduped master when running all queries', function () {
    Carbon::setTestNow('2026-05-05 00:07:38');

    writeQueriesFile($this->queriesPath, [
        ['id' => 'tomato', 'label' => 'Tomato', 'query' => 'tomato segmentation', 'limit' => 10],
        ['id' => 'vision', 'label' => 'Vision', 'query' => 'vision transformers', 'limit' => 5],
    ]);

    $workA = makeWork(
        title: 'Paper A',
        provider: 'openalex',
        primaryId: new WorkId(WorkIdNamespace::DOI, '10.1234/abc'),
        year: 2024,
        abstract: 'Example abstract.'
    );

    $workB = makeWork(
        title: 'Paper B',
        provider: 'arxiv',
        primaryId: new WorkId(WorkIdNamespace::ARXIV, 'arxiv:2401.00001'),
        year: 2023,
        abstract: 'Another abstract.'
    );

    bindAggregator(function (SearchQuery $query) use ($workA, $workB): AggregatedResult {
        $works = $query->term->value === 'tomato segmentation'
            ? [$workA]
            : [$workA, $workB];

        return new AggregatedResult(
            corpus: CorpusSlice::fromWorks(...$works),
            providerStats: [],
            totalRaw: count($works)
        );
    });

    $this->artisan('nexus:search', ['--all' => true])
        ->assertExitCode(0);

    $runFileTomato = "{$this->runsDir}/tomato_20260505_000738.json";
    $runFileVision = "{$this->runsDir}/vision_20260505_000738.json";
    $globalFile = "{$this->runsDir}/all_20260505_000738.json";
    $latestPointer = "{$this->runsDir}/latest.json";

    expect(File::exists($runFileTomato))->toBeTrue();
    expect(File::exists($runFileVision))->toBeTrue();
    expect(File::exists($globalFile))->toBeTrue();
    expect(File::exists($latestPointer))->toBeTrue();

    $globalPayload = json_decode(File::get($globalFile), true);
    expect($globalPayload)->toHaveCount(2);

    $expectedA = ScholarlyWorkDto::fromDomain($workA);
    $expectedB = ScholarlyWorkDto::fromDomain($workB);

    $matchA = array_values(array_filter($globalPayload, fn ($item) => ($item['title'] ?? null) === 'Paper A'));
    $matchB = array_values(array_filter($globalPayload, fn ($item) => ($item['title'] ?? null) === 'Paper B'));

    expect($matchA)->toHaveCount(1);
    expect($matchB)->toHaveCount(1);
    expect($matchA[0])->toMatchArray($expectedA);
    expect($matchB[0])->toMatchArray($expectedB);
    expect($matchA[0])->toHaveKeys(['query_id', 'query_metadata']);
    expect($matchB[0])->toHaveKeys(['query_id', 'query_metadata']);

    $latestPayload = json_decode(File::get($latestPointer), true);
    expect($latestPayload['file'])->toBe('storage/runs/all_20260505_000738.json');
    expect($latestPayload['run_at'])->toBe(Carbon::now()->toIso8601String());
});

