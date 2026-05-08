<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->wikiDir = base_path('docs/wiki');
    $this->papersDir = base_path('docs/wiki/papers');
    $this->logFile = base_path('docs/wiki/log.md');

    $this->runsDir = storage_path('runs');
    $this->latestPointer = "{$this->runsDir}/latest.json";

    $this->originalLog = File::exists($this->logFile) ? File::get($this->logFile) : null;
    $this->hadLog = File::exists($this->logFile);

    $this->hadLatestPointer = File::exists($this->latestPointer);
    $this->originalLatest = $this->hadLatestPointer ? File::get($this->latestPointer) : null;

    $this->createdFiles = [];

    Carbon::setTestNow('2026-05-05 00:00:00');
});

afterEach(function () {
    foreach ($this->createdFiles as $path) {
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    if ($this->hadLog) {
        File::put($this->logFile, $this->originalLog);
    } elseif (File::exists($this->logFile)) {
        File::delete($this->logFile);
    }

    if ($this->hadLatestPointer) {
        File::put($this->latestPointer, $this->originalLatest);
    } elseif (File::exists($this->latestPointer)) {
        File::delete($this->latestPointer);
    }

    Carbon::setTestNow();
});

function makeRunPayload(string $title, int $year, string $doi, string $source): array
{
    return [
        'ids' => [
            ['ns' => 'doi', 'val' => $doi],
        ],
        'title' => $title,
        'authors' => [
            ['family' => 'Doe', 'given' => 'Jane'],
            ['family' => 'Smith', 'given' => 'John'],
        ],
        'year' => $year,
        'venue' => null,
        'abstract' => 'Example abstract.',
        'citedByCount' => 12,
        'isRetracted' => false,
        'sourceProvider' => $source,
        'retrievedAt' => Carbon::now()->toIso8601String(),
        'rawData' => null,
    ];
}

function writeRunFile(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!File::isDirectory($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    File::put($path, json_encode($payload, JSON_PRETTY_PRINT));
}

test('fails when latest pointer is missing and no file argument is provided', function () {
    if (File::exists($this->latestPointer)) {
        File::delete($this->latestPointer);
    }

    $this->artisan('nexus:ingest')
        ->assertExitCode(1);
});

test('creates a paper page from a provided run file', function () {
    $payload = [
        makeRunPayload('Paper A', 2024, '10.1234/abc', 'openalex'),
    ];

    $runFile = "{$this->runsDir}/paper_a_20260505_000000.json";
    writeRunFile($runFile, $payload);
    $this->createdFiles[] = $runFile;

    $this->artisan('nexus:ingest', ['file' => $runFile])
        ->assertExitCode(0);

    $paperPath = "{$this->papersDir}/paper-a-2024.md";
    expect(File::exists($paperPath))->toBeTrue();
    $this->createdFiles[] = $paperPath;

    $content = File::get($paperPath);
    expect($content)->toContain("title: 'Paper A'");
    expect($content)->toContain('year: 2024');
    expect($content)->toContain('doi: 10.1234/abc');
    expect($content)->toContain('source: openalex');
    expect($content)->toContain("- 'Jane Doe'");
    expect($content)->toContain("- 'John Smith'");
    expect($content)->toContain('## Summary');

    $log = File::get($this->logFile);
    expect($log)->toContain('## [2026-05-05] ingest | Paper A');
});

test('skips existing paper pages without overwriting', function () {
    if (!File::isDirectory($this->papersDir)) {
        File::makeDirectory($this->papersDir, 0755, true);
    }

    $paperPath = "{$this->papersDir}/paper-a-2024.md";
    File::put($paperPath, 'existing content');
    $this->createdFiles[] = $paperPath;

    $payload = [
        makeRunPayload('Paper A', 2024, '10.1234/abc', 'openalex'),
    ];

    $runFile = "{$this->runsDir}/paper_a_20260505_000000.json";
    writeRunFile($runFile, $payload);
    $this->createdFiles[] = $runFile;

    $this->artisan('nexus:ingest', ['file' => $runFile])
        ->assertExitCode(0);

    $content = File::get($paperPath);
    expect($content)->toBe('existing content');

    $log = File::exists($this->logFile) ? File::get($this->logFile) : '';
    expect($log)->not->toContain('## [2026-05-05] ingest | Paper A');
});

test('uses latest.json when file argument is missing', function () {
    $payload = [
        makeRunPayload('Paper B', 2022, '10.9999/xyz', 'arxiv'),
    ];

    $runFile = "{$this->runsDir}/paper_b_20260505_000000.json";
    writeRunFile($runFile, $payload);
    $this->createdFiles[] = $runFile;

    if (!File::isDirectory($this->runsDir)) {
        File::makeDirectory($this->runsDir, 0755, true);
    }
    File::put($this->latestPointer, json_encode([
        'file' => 'storage/runs/paper_b_20260505_000000.json',
        'run_at' => Carbon::now()->toIso8601String(),
    ], JSON_PRETTY_PRINT));
    $this->createdFiles[] = $this->latestPointer;

    $this->artisan('nexus:ingest')
        ->assertExitCode(0);

    $paperPath = "{$this->papersDir}/paper-b-2022.md";
    expect(File::exists($paperPath))->toBeTrue();
    $this->createdFiles[] = $paperPath;
});

test('fails when latest.json points to a missing run file', function () {
    if (!File::isDirectory($this->runsDir)) {
        File::makeDirectory($this->runsDir, 0755, true);
    }
    File::put($this->latestPointer, json_encode([
        'file' => 'storage/runs/missing.json',
        'run_at' => Carbon::now()->toIso8601String(),
    ], JSON_PRETTY_PRINT));
    $this->createdFiles[] = $this->latestPointer;

    $this->artisan('nexus:ingest')
        ->assertExitCode(1);
});

