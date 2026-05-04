<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class NexusWikiInit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexus:wiki-init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize the docs/wiki/ folder structure and seed files.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $basePath = base_path('docs/wiki');

        $directories = [
            'papers',
            'concepts',
            'synthesis',
        ];

        // Create base directory
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
            $this->info("Created directory: {$basePath}");
        }

        // Create subdirectories and .gitkeep
        foreach ($directories as $dir) {
            $dirPath = "{$basePath}/{$dir}";
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
                File::put("{$dirPath}/.gitkeep", "");
                $this->info("Created directory: {$dirPath}");
            }
        }

        // Create SCHEMA.md from template
        $schemaFile = "{$basePath}/SCHEMA.md";
        if (!File::exists($schemaFile)) {
            $templatePath = base_path('docs/wiki-schema.md');
            if (File::exists($templatePath)) {
                File::copy($templatePath, $schemaFile);
                $this->info("Created seed file: {$schemaFile}");
            } else {
                $this->warn("Template docs/wiki-schema.md not found. Created empty SCHEMA.md");
                File::put($schemaFile, "# Wiki Schema\n");
            }
        }

        // Create index.md
        $indexFile = "{$basePath}/index.md";
        if (!File::exists($indexFile)) {
            File::put($indexFile, "# Research Wiki Index\n\n## Core Concepts\n\n## Recent Papers\n\n## Synthesis Reports\n");
            $this->info("Created seed file: {$indexFile}");
        }

        // Create log.md
        $logFile = "{$basePath}/log.md";
        if (!File::exists($logFile)) {
            $date = date('Y-m-d');
            File::put($logFile, "# Wiki Activity Log\n\n| Date | Action | Details |\n|------|--------|---------|\n| {$date} | Init | Wiki initialized |\n");
            $this->info("Created seed file: {$logFile}");
        }

        \Laravel\Prompts\info('Wiki initialization complete.');

        return self::SUCCESS;
    }
}
