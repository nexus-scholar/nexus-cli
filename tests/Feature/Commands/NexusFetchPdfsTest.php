<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Nexus\Dissemination\Domain\Port\DownloadResult;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Domain\Port\PdfDownloaderPort;
use Nexus\Dissemination\Infrastructure\PdfSource\DirectPdfSource;

beforeEach(function () {
    $this->artisan('migrate:fresh')->run();

    $this->runsDir = storage_path('runs');
    $this->screensDir = storage_path('screens');

    $this->createdPaths = [];

    Storage::fake('public');
    config(['nexus.dissemination.pdf_storage_disk' => 'public']);
    app()->instance(FullTextSourceCollection::class, new FullTextSourceCollection(new DirectPdfSource));
    app()->instance(PdfDownloaderPort::class, new class implements PdfDownloaderPort
    {
        public function download(string $url): DownloadResult
        {
            return new DownloadResult('%PDF-1.4 test', 200, 'application/pdf');
        }
    });

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
});

function writeJson(string $path, array $payload): void
{
    $dir = dirname($path);
    if (! File::isDirectory($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    File::put($path, json_encode($payload, JSON_PRETTY_PRINT));
}

test('retrieves full text for included titles', function (string $command) {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeJson($runFile, [[
        'ids' => [['ns' => 'doi', 'val' => '10.1234/abc']],
        'title' => 'Tomato instance segmentation study',
        'authors' => [],
        'year' => 2024,
        'venue' => null,
        'abstract' => 'A full-text retrieval test paper.',
        'citedByCount' => 3,
        'isRetracted' => false,
        'sourceProvider' => 'test',
        'rawData' => [
            'direct_pdf_url' => 'https://example.org/paper.pdf',
        ],
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

    $this->artisan($command, ['screen' => $screenFile])
        ->assertExitCode(0);

    $files = Storage::disk('public')->allFiles('full-text/all_20260506_000000');
    expect($files)->toHaveCount(2);

    $pdfPath = collect($files)->first(fn (string $file): bool => str_ends_with($file, '.pdf'));
    expect($pdfPath)->not->toBeNull();

    $manifestPath = 'full-text/all_20260506_000000/manifest.json';
    expect(Storage::disk('public')->exists($manifestPath))->toBeTrue();

    $manifest = json_decode(Storage::disk('public')->get($manifestPath), true);
    expect($manifest)
        ->toHaveCount(1)
        ->and($manifest[0]['status'])->toBe('success')
        ->and($manifest[0]['source_alias'])->toBe('direct')
        ->and($manifest[0]['artifact_path'])->toBe($pdfPath)
        ->and(DB::table('pdf_fetches')->where('status', 'success')->count())->toBe(1);
})->with([
    'preferred command' => 'nexus:fetch-full-text',
    'legacy command' => 'nexus:fetch-pdfs',
]);

test('retrieves full text with json summary output', function (string $command) {
    $runFile = "{$this->runsDir}/all_20260506_000001.json";
    writeJson($runFile, [[
        'ids' => [['ns' => 'doi', 'val' => '10.1234/json']],
        'title' => 'Tomato full text JSON summary study',
        'authors' => [],
        'year' => 2024,
        'venue' => null,
        'abstract' => 'A full-text JSON output test paper.',
        'citedByCount' => 3,
        'isRetracted' => false,
        'sourceProvider' => 'test',
        'rawData' => [
            'direct_pdf_url' => 'https://example.org/json-paper.pdf',
        ],
    ]]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    $screenFile = "{$this->screensDir}/all_20260506_000001.json";
    writeJson($screenFile, [
        'run_file' => 'storage/runs/all_20260506_000001.json',
        'decisions' => [
            ['title' => 'Tomato full text JSON summary study', 'included' => true],
        ],
    ]);
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $exitCode = Artisan::call($command, [
        'screen' => $screenFile,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())
        ->toContain('"screen_file": "storage/screens/all_20260506_000001.json"')
        ->toContain('"run_id": "all_20260506_000001"')
        ->toContain('"destination": "full-text/all_20260506_000001"')
        ->toContain('"manifest_path": "full-text/all_20260506_000001/manifest.json"')
        ->toContain('"total": 1')
        ->toContain('"success": 1')
        ->toContain('"failed": 0')
        ->toContain('"skipped": 0');
})->with([
    'preferred command' => 'nexus:fetch-full-text',
    'legacy command' => 'nexus:fetch-pdfs',
]);
