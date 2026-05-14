<?php

namespace App\Search;

use Illuminate\Support\Facades\File;

final class SearchRunWriter
{
    public function writeRun(string $prefix, string $timestamp, array $payload): SearchRunFile
    {
        $runsDir = storage_path('runs');
        if (! File::isDirectory($runsDir)) {
            File::makeDirectory($runsDir, 0755, true);
        }

        $filename = "{$prefix}_{$timestamp}.json";
        $absolute = "{$runsDir}/{$filename}";
        $relative = "storage/runs/{$filename}";

        File::put($absolute, $this->encodeJson($payload));

        return new SearchRunFile($absolute, $relative);
    }

    public function writeLatestPointer(SearchRunFile $runFile): void
    {
        $runsDir = storage_path('runs');
        if (! File::isDirectory($runsDir)) {
            File::makeDirectory($runsDir, 0755, true);
        }

        File::put("{$runsDir}/latest.json", $this->encodeJson([
            'file' => $runFile->relative,
            'run_at' => now()->toIso8601String(),
        ]));
    }

    private function encodeJson(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
