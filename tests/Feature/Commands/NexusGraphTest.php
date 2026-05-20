<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->runsDir = storage_path('runs');
    $this->graphsDir = storage_path('graphs');
    $this->latestPointer = "{$this->runsDir}/latest.json";
    $this->createdFiles = [];

    $this->hadLatestPointer = File::exists($this->latestPointer);
    $this->originalLatestContent = $this->hadLatestPointer ? File::get($this->latestPointer) : null;

    Carbon::setTestNow('2026-05-20 12:00:00');
});

afterEach(function () {
    foreach ($this->createdFiles as $path) {
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    if ($this->hadLatestPointer) {
        File::put($this->latestPointer, $this->originalLatestContent);
    } elseif (File::exists($this->latestPointer)) {
        File::delete($this->latestPointer);
    }

    Carbon::setTestNow();
});

function writeGraphCommandRun(string $path, array $payload): void
{
    $dir = dirname($path);
    if (! File::isDirectory($dir)) {
        File::makeDirectory($dir, 0755, true);
    }

    File::put($path, json_encode($payload, JSON_PRETTY_PRINT));
}

function graphCommandWork(string $title, string $doi, ?string $openAlexId = null, array $rawData = []): array
{
    $ids = [
        ['ns' => 'doi', 'val' => $doi],
    ];

    if ($openAlexId !== null) {
        $ids[] = ['ns' => 'openalex', 'val' => $openAlexId];
    }

    return [
        'ids' => $ids,
        'title' => $title,
        'authors' => [],
        'year' => 2024,
        'venue' => null,
        'abstract' => 'Example abstract.',
        'citedByCount' => 0,
        'isRetracted' => false,
        'sourceProvider' => 'openalex',
        'retrievedAt' => '2026-05-20T12:00:00+00:00',
        'rawData' => $rawData,
    ];
}

test('builds and saves citation graph from run JSON relationships', function () {
    $runFile = "{$this->runsDir}/graph_run.json";
    $outputFile = "{$this->graphsDir}/graph_run_citation.json";

    writeGraphCommandRun($runFile, [
        graphCommandWork('Paper A', '10.1000/a', 'https://openalex.org/W1', [
            'referenced_works' => ['https://openalex.org/W2'],
        ]),
        graphCommandWork('Paper B', '10.1000/b', 'https://openalex.org/W2'),
        graphCommandWork('Paper C', '10.1000/c', null, [
            'references' => [['DOI' => '10.1000/b']],
        ]),
    ]);
    $this->createdFiles[] = $runFile;
    $this->createdFiles[] = $outputFile;

    $this->artisan('nexus:graph', ['run' => $runFile, '--project' => 'project-1'])
        ->expectsOutputToContain('Relationships extracted: 2')
        ->expectsOutputToContain('Edges: 2')
        ->expectsOutputToContain('Saved:')
        ->assertExitCode(0);

    expect(File::exists($outputFile))->toBeTrue();

    $payload = json_decode(File::get($outputFile), true);
    $edges = array_map(
        fn (array $edge): string => $edge['citing'] . '->' . $edge['cited'],
        $payload['graph']['edges'],
    );

    expect($payload['project_id'])->toBe('project-1')
        ->and($payload['type'])->toBe('citation')
        ->and($payload['metrics']['node_count'])->toBe(3)
        ->and($payload['metrics']['edge_count'])->toBe(2)
        ->and($edges)->toContain('doi:10.1000/a->doi:10.1000/b', 'doi:10.1000/c->doi:10.1000/b');
});

test('uses latest pointer and supports dry run', function () {
    $runFile = "{$this->runsDir}/latest_graph_run.json";
    $outputFile = "{$this->graphsDir}/latest_graph_run_citation.json";

    writeGraphCommandRun($runFile, [
        graphCommandWork('Paper A', '10.1000/a'),
        graphCommandWork('Paper B', '10.1000/b'),
    ]);
    File::put($this->latestPointer, json_encode(['file' => 'storage/runs/latest_graph_run.json'], JSON_PRETTY_PRINT));

    $this->createdFiles[] = $runFile;
    $this->createdFiles[] = $outputFile;

    $this->artisan('nexus:graph', ['--dry-run' => true])
        ->expectsOutputToContain('Run file: storage/runs/latest_graph_run.json')
        ->expectsOutputToContain('Relationships extracted: 0')
        ->expectsOutputToContain('Dry run: no graph file written.')
        ->assertExitCode(0);

    expect(File::exists($outputFile))->toBeFalse();
});

test('fails when only one shortest path endpoint is provided', function () {
    $runFile = "{$this->runsDir}/path_graph_run.json";

    writeGraphCommandRun($runFile, [
        graphCommandWork('Paper A', '10.1000/a'),
    ]);
    $this->createdFiles[] = $runFile;

    $this->artisan('nexus:graph', ['run' => $runFile, '--source' => 'doi:10.1000/a'])
        ->assertExitCode(1);
});
