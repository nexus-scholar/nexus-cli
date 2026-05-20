<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->runsDir = storage_path('runs');
    $this->screensDir = storage_path('screens');
    $this->pdfsDir = storage_path('pdfs');

    $this->createdPaths = [];

    Carbon::setTestNow('2026-05-06 00:00:00');
});

afterEach(function () {
    foreach ($this->createdPaths as $path) {
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        } elseif (File::exists($path)) {
            File::delete($path);
        }
    }

    Carbon::setTestNow();
    Http::preventStrayRequests();
});

function writeJson(string $path, array $payload): void
{
    $dir = dirname($path);
    if (! File::isDirectory($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    File::put($path, json_encode($payload, JSON_PRETTY_PRINT));
}

test('downloads pdfs for included titles', function () {
    Http::fake([
        'https://api.openalex.org/works/*' => Http::response([
            'open_access' => ['oa_url' => 'https://example.org/paper.pdf'],
        ], 200),
        'https://example.org/paper.pdf' => Http::response('%PDF-1.4 test', 200),
    ]);

    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeJson($runFile, [[
        'title' => 'Tomato instance segmentation study',
        'year' => 2024,
        'ids' => [['ns' => 'doi', 'val' => '10.1234/abc']],
    ]]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    writeJson($screenFile, [
        'run_file' => 'storage/runs/all_20260506_000000.json',
        'decisions' => [
            ['title' => 'Tomato instance segmentation study', 'included' => true],
        ],
    ]);
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $this->artisan('nexus:fetch-pdfs', ['screen' => $screenFile])
        ->assertExitCode(0);

    $pdfPath = "{$this->pdfsDir}/all_20260506_000000/tomato-instance-segmentation-study-2024.pdf";
    expect(File::exists($pdfPath))->toBeTrue();
    $this->createdPaths[] = $pdfPath;

    $manifest = "{$this->pdfsDir}/all_20260506_000000/manifest.json";
    expect(File::exists($manifest))->toBeTrue();
    $this->createdPaths[] = $manifest;
    $this->createdPaths[] = "{$this->pdfsDir}/all_20260506_000000";
});
