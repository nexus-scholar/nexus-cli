<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->runsDir = storage_path('runs');
    if (!File::isDirectory($this->runsDir)) {
        File::makeDirectory($this->runsDir, 0755, true);
    }

    $this->latestPointer = "{$this->runsDir}/latest.json";
    $this->hadLatestPointer = File::exists($this->latestPointer);
    $this->originalLatestContent = $this->hadLatestPointer ? File::get($this->latestPointer) : null;
});

afterEach(function () {
    if ($this->hadLatestPointer) {
        File::put($this->latestPointer, $this->originalLatestContent);
    } elseif (File::exists($this->latestPointer)) {
        File::delete($this->latestPointer);
    }
});

test('shows stats for latest run file', function () {
    $runFile = "{$this->runsDir}/sample_run.json";
    $payload = [
        ['title' => 'Paper A', 'abstract' => 'A', 'year' => 2024, 'sourceProvider' => 'openalex', 'query_id' => 'Q1'],
        ['title' => 'Paper B', 'abstract' => 'B', 'year' => 2023, 'sourceProvider' => 'arxiv', 'query_id' => 'Q1'],
    ];

    File::put($runFile, json_encode($payload, JSON_PRETTY_PRINT));
    File::put($this->latestPointer, json_encode(['file' => 'storage/runs/sample_run.json'], JSON_PRETTY_PRINT));

    $this->artisan('nexus:run-stats')
        ->expectsOutputToContain('Total works: 2')
        ->expectsOutputToContain('openalex')
        ->expectsOutputToContain('arxiv')
        ->expectsOutputToContain('Q1')
        ->assertExitCode(0);
});

