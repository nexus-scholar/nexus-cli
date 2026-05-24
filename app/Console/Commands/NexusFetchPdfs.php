<?php

namespace App\Console\Commands;

use App\FullText\FullTextRetrievalReport;
use App\FullText\ScreenedRunFullTextRetriever;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
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
    protected $signature = 'nexus:fetch-pdfs
        {screen? : path to screen JSON, defaults to latest}
        {--destination= : storage-disk folder, defaults to full-text/{run_id}}
        {--max-attempts=2 : max download attempts per source}
        {--max-bytes=50000000 : max artifact size in bytes}
        {--cooldown=3600 : seconds before retrying a recently failed source URL}
        {--json : output a machine-readable retrieval summary}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve legal open-access full text for included papers through nexus-scholar/core.';

    public function handle(ScreenedRunFullTextRetriever $retriever): int
    {
        $screenFile = $this->resolveScreenFile();
        if ($screenFile === null) {
            return self::FAILURE;
        }

        try {
            $report = $retriever->retrieve(
                screenFile: $screenFile,
                destination: $this->nullableStringOption('destination'),
                maxDownloadAttempts: $this->intOption('max-attempts', min: 1),
                maxBytes: $this->intOption('max-bytes', min: 1),
                failedAttemptCooldownSeconds: $this->intOption('cooldown', min: 0),
            );
        } catch (\Throwable $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = $this->summary($screenFile, $report);
        if ((bool) $this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($report->total() === 0) {
            warning('No included papers found in screen file.');
            $this->line("Screen: {$summary['screen_file']}");
            $this->line("Destination: {$summary['destination']}");
            $this->line("Manifest: {$summary['manifest_path']}");

            return self::SUCCESS;
        }

        $this->line("Screen: {$summary['screen_file']}");
        $this->line("Destination: {$summary['destination']}");
        info(sprintf(
            'Retrieved full text: %d total, %d success, %d failed, %d skipped.',
            $summary['total'],
            $summary['success'],
            $summary['failed'],
            $summary['skipped'],
        ));
        $this->line("Manifest: {$summary['manifest_path']}");

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

    private function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    private function nullableStringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function intOption(string $key, int $min): int
    {
        $value = filter_var($this->option($key), FILTER_VALIDATE_INT);

        if (! is_int($value) || $value < $min) {
            throw new \InvalidArgumentException("--{$key} must be an integer greater than or equal to {$min}.");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(string $screenFile, FullTextRetrievalReport $report): array
    {
        return [
            'screen_file' => $this->displayPath($screenFile),
            'run_id' => $report->runId,
            'destination' => $report->destination,
            'manifest_path' => $report->manifestPath,
            'total' => $report->total(),
            'success' => $report->countStatus('success'),
            'failed' => $report->countStatus('failure'),
            'skipped' => $report->countStatus('skipped'),
        ];
    }

    private function displayPath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return Str::startsWith($path, $base)
            ? str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base)))
            : $path;
    }
}
