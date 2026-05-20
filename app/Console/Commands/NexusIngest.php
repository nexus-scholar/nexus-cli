<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class NexusIngest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexus:ingest {file? : path to run JSON, defaults to latest}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create wiki paper pages from a run JSON file without overwriting existing pages.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $runFile = $this->resolveRunFile();
        if ($runFile === null) {
            return self::FAILURE;
        }

        $runData = json_decode(File::get($runFile), true);
        if (! is_array($runData)) {
            error("Run file is not valid JSON: {$runFile}");

            return self::FAILURE;
        }

        $papersDir = base_path('docs/wiki/papers');
        $logFile = base_path('docs/wiki/log.md');
        $this->ensureWikiFolders($papersDir, $logFile);

        $createdCount = 0;
        foreach ($runData as $work) {
            if (! is_array($work)) {
                continue;
            }

            $title = (string) ($work['title'] ?? '');
            if (trim($title) === '') {
                warning('Skipping entry with empty title.');

                continue;
            }

            $year = $work['year'] ?? null;
            $slug = $this->makeSlug($title, $year);
            $paperPath = "{$papersDir}/{$slug}.md";

            if (File::exists($paperPath)) {
                warning("Skipping existing page: {$paperPath}");

                continue;
            }

            $payload = $this->buildPaperContent($work);
            File::put($paperPath, $payload);
            $createdCount++;

            $this->appendLogEntry($logFile, $title);
            info("Created: {$paperPath}");
        }

        $this->line("Created {$createdCount} paper page(s).");

        return self::SUCCESS;
    }

    private function resolveRunFile(): ?string
    {
        $fileArg = $this->argument('file');
        if ($fileArg) {
            $filePath = $this->normalizePath($fileArg);
            if (! File::exists($filePath)) {
                error("Run file not found: {$filePath}");

                return null;
            }

            return $filePath;
        }

        $latestPointer = storage_path('runs/latest.json');
        if (! File::exists($latestPointer)) {
            error('latest.json pointer not found. Run nexus:search or pass a run file path.');

            return null;
        }

        $latestData = json_decode(File::get($latestPointer), true);
        $latestFile = $latestData['file'] ?? null;
        if (! is_string($latestFile) || $latestFile === '') {
            error('latest.json pointer is invalid.');

            return null;
        }

        $resolved = base_path($latestFile);
        if (! File::exists($resolved)) {
            error("Run file referenced by latest.json not found: {$resolved}");

            return null;
        }

        return $resolved;
    }

    private function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    private function ensureWikiFolders(string $papersDir, string $logFile): void
    {
        if (! File::isDirectory($papersDir)) {
            File::makeDirectory($papersDir, 0755, true);
        }

        if (! File::exists($logFile)) {
            $logDir = dirname($logFile);
            if (! File::isDirectory($logDir)) {
                File::makeDirectory($logDir, 0755, true);
            }
            File::put($logFile, "# Wiki Activity Log\n\n| Date | Action | Details |\n|------|--------|---------|\n");
        }
    }

    private function makeSlug(string $title, ?int $year): string
    {
        $base = Str::slug($title);
        $suffix = $year ? (string) $year : 'undated';

        return $base === '' ? "untitled-{$suffix}" : "{$base}-{$suffix}";
    }

    private function buildPaperContent(array $work): string
    {
        $title = (string) ($work['title'] ?? '');
        $year = $work['year'] ?? null;
        $source = (string) ($work['sourceProvider'] ?? '');
        $doi = $this->extractDoi($work['ids'] ?? []);

        $authors = $this->formatAuthors($work['authors'] ?? []);

        $frontmatter = [
            'title' => $title,
            'year' => $year,
            'authors' => $authors,
            'doi' => $doi,
            'source' => $source,
            'method' => '',
            'dataset' => '',
            'metric' => '',
            'thesis_relevance' => '',
        ];

        $yaml = Yaml::dump($frontmatter, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        return "---\n{$yaml}---\n\n".
            "## Summary\n\n".
            "(2-3 sentences: what problem, what method, what result)\n\n".
            "## Method\n\n".
            "(architecture, training setup, key design choices)\n\n".
            "## Dataset and Results\n\n".
            "(dataset name, size, label availability, reported numbers)\n\n".
            "## Thesis Connection\n\n".
            "> How does this paper support or challenge the thesis argument?\n".
            "> Does it address annotation scarcity? If not, say so explicitly.\n\n".
            "## Contradictions and Gaps\n\n".
            "> What does this paper NOT do that your paper does?\n".
            "> Is the dataset fully labeled? Controlled conditions only?\n".
            "> No field condition evaluation? -> flag this\n";
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

    private function formatAuthors(array $authors): array
    {
        $names = [];
        foreach ($authors as $author) {
            if (! is_array($author)) {
                continue;
            }

            $family = trim((string) ($author['family'] ?? ''));
            $given = trim((string) ($author['given'] ?? ''));
            $full = $given !== '' ? "{$given} {$family}" : $family;

            if ($full !== '') {
                $names[] = $full;
            }
        }

        return $names;
    }

    private function appendLogEntry(string $logFile, string $title): void
    {
        $date = now()->format('Y-m-d');
        $entry = "## [{$date}] ingest | {$title}\n";

        File::append($logFile, $entry);
    }
}
