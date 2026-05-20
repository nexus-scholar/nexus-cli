<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class NexusFetchPdfs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexus:fetch-pdfs {screen? : path to screen JSON, defaults to latest}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch PDFs for included papers and store them under storage/pdfs/{run_id}/.';

    public function handle(): int
    {
        $screenFile = $this->resolveScreenFile();
        if ($screenFile === null) {
            return self::FAILURE;
        }

        $screenData = json_decode(File::get($screenFile), true);
        if (! is_array($screenData)) {
            error("Screen file is not valid JSON: {$screenFile}");

            return self::FAILURE;
        }

        $runFile = $screenData['run_file'] ?? null;
        if (! is_string($runFile) || $runFile === '') {
            error('Screen file does not include run_file path.');

            return self::FAILURE;
        }

        $resolvedRunFile = $this->normalizePath($runFile);
        if (! File::exists($resolvedRunFile)) {
            error("Run file not found: {$resolvedRunFile}");

            return self::FAILURE;
        }

        $runData = json_decode(File::get($resolvedRunFile), true);
        if (! is_array($runData)) {
            error("Run file is not valid JSON: {$resolvedRunFile}");

            return self::FAILURE;
        }

        $includedTitles = $this->includedTitles($screenData['decisions'] ?? []);
        if ($includedTitles === []) {
            warning('No included papers found in screen file.');

            return self::SUCCESS;
        }

        $runId = pathinfo($resolvedRunFile, PATHINFO_FILENAME);
        $pdfDir = storage_path("pdfs/{$runId}");
        if (! File::isDirectory($pdfDir)) {
            File::makeDirectory($pdfDir, 0755, true);
        }

        $manifest = [];
        foreach ($runData as $work) {
            if (! is_array($work)) {
                continue;
            }

            $title = (string) ($work['title'] ?? '');
            if ($title === '' || ! isset($includedTitles[$title])) {
                continue;
            }

            $doi = $this->extractDoi($work['ids'] ?? []);
            if ($doi === '') {
                $manifest[] = [
                    'title' => $title,
                    'status' => 'missing_doi',
                ];

                continue;
            }

            $pdfUrl = $this->resolvePdfUrlFromOpenAlex($doi);
            if ($pdfUrl === null) {
                warning("No PDF URL found for DOI: {$doi}");
                $manifest[] = [
                    'title' => $title,
                    'doi' => $doi,
                    'status' => 'no_pdf_url',
                ];

                continue;
            }

            $year = is_int($work['year'] ?? null) ? (int) $work['year'] : null;
            $slug = $this->makeSlug($title, $year);
            $pdfPath = "{$pdfDir}/{$slug}.pdf";

            $response = Http::get($pdfUrl);
            if (! $response->successful()) {
                $manifest[] = [
                    'title' => $title,
                    'doi' => $doi,
                    'status' => 'download_failed',
                    'pdf_url' => $pdfUrl,
                ];

                continue;
            }

            File::put($pdfPath, $response->body());
            $manifest[] = [
                'title' => $title,
                'doi' => $doi,
                'status' => 'downloaded',
                'pdf_path' => $this->toRelativePath($pdfPath),
                'pdf_url' => $pdfUrl,
            ];
            info("Downloaded: {$pdfPath}");
        }

        File::put("{$pdfDir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));
        $this->line("Saved manifest: {$pdfDir}/manifest.json");

        return self::SUCCESS;
    }

    private function resolveScreenFile(): ?string
    {
        $screenArg = $this->argument('screen');
        if ($screenArg) {
            $screenPath = $this->normalizePath($screenArg);
            if (! File::exists($screenPath)) {
                error("Screen file not found: {$screenPath}");

                return null;
            }

            return $screenPath;
        }

        $latestPointer = storage_path('runs/latest.json');
        if (! File::exists($latestPointer)) {
            error('latest.json pointer not found. Run nexus:search or pass a screen file path.');

            return null;
        }

        $latest = json_decode(File::get($latestPointer), true);
        $latestFile = $latest['file'] ?? null;
        if (! is_string($latestFile) || $latestFile === '') {
            error('latest.json pointer is invalid.');

            return null;
        }

        $runId = pathinfo($latestFile, PATHINFO_FILENAME);
        $screenPath = storage_path("screens/{$runId}.json");
        if (! File::exists($screenPath)) {
            error("Screen file not found: {$screenPath}");

            return null;
        }

        return $screenPath;
    }

    private function includedTitles(array $decisions): array
    {
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

    private function extractDoi(array $ids): string
    {
        foreach ($ids as $id) {
            if (! is_array($id)) {
                continue;
            }

            if (($id['ns'] ?? '') === 'doi' && ($id['val'] ?? '') !== '') {
                return (string) $id['val'];
            }
        }

        return '';
    }

    private function resolvePdfUrlFromOpenAlex(string $doi): ?string
    {
        $encoded = rawurlencode("doi:{$doi}");
        $url = "https://api.openalex.org/works/{$encoded}";

        $response = Http::get($url);
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        $openAccess = $data['open_access'] ?? [];
        $oaUrl = $openAccess['oa_url'] ?? null;
        if (is_string($oaUrl) && $oaUrl !== '') {
            return $oaUrl;
        }

        $primaryLocation = $data['primary_location'] ?? [];
        $pdfUrl = $primaryLocation['pdf_url'] ?? null;
        if (is_string($pdfUrl) && $pdfUrl !== '') {
            return $pdfUrl;
        }

        return null;
    }

    private function makeSlug(string $title, ?int $year): string
    {
        $base = Str::slug($title);
        $suffix = $year ? (string) $year : 'undated';

        return $base === '' ? "untitled-{$suffix}" : "{$base}-{$suffix}";
    }

    private function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    private function toRelativePath(string $path): string
    {
        $base = base_path();
        if (Str::startsWith($path, $base)) {
            return ltrim(Str::after($path, $base), '\\/');
        }

        return $path;
    }
}
