<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

#[Signature('nexus:status')]
#[Description('Shows live research dashboard.')]
class NexusCliStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        info('Nexus Research Dashboard');

        $this->displayTime();
        $this->displayDatasetAndBaseline();
        $this->displayLatestRun();
        $this->displayWikiHealth();

        return self::SUCCESS;
    }

    private function displayTime()
    {
        $startDateStr = config('nexus.project_start_date', '2026-05-01');
        $startDate = Carbon::parse($startDateStr);
        $now = Carbon::now();
        $weeks = (int) $startDate->diffInWeeks($now) + 1; // Week 1 starts on start date

        $this->line(" 📅 Current Week: <options=bold>Week {$weeks}</> (started {$startDate->format('Y-m-d')})");
        $this->newLine();
    }

    private function displayDatasetAndBaseline()
    {
        $baselinePath = storage_path('baseline.json');

        if (File::exists($baselinePath)) {
            $data = json_decode(File::get($baselinePath), true);
            $labeled = $data['labeled_count'] ?? 0;
            $total = 3616; // From thesis spec
            $unlabeled = $total - $labeled;

            $this->line(" 🍅 Dataset: <info>{$labeled} labeled</info> | <comment>{$unlabeled} unlabeled</comment> (Total: {$total})");

            $model = $data['model'] ?? 'Unknown';
            $segmAp = $data['segm_ap'] ?? 'N/A';
            $bboxAp = $data['bbox_ap'] ?? 'N/A';

            $this->line(" 📊 Latest Baseline: <options=bold>{$model}</> | Segm AP: <info>{$segmAp}%</info> | Bbox AP: <info>{$bboxAp}%</info>");
        } else {
            warning(' 🍅 Dataset & Baseline: storage/baseline.json not found.');
        }
        $this->newLine();
    }

    private function displayLatestRun()
    {
        $latestPath = storage_path('runs/latest.json');

        if (File::exists($latestPath)) {
            $data = json_decode(File::get($latestPath), true);
            $targetFile = $data['file'] ?? null;

            if ($targetFile && File::exists(base_path($targetFile))) {
                $runData = json_decode(File::get(base_path($targetFile)), true);
                $paperCount = is_array($runData) ? count($runData) : 0;
                $filename = basename($targetFile);
                $this->line(" 🔍 Latest Search Run: <options=bold>{$filename}</> | <info>{$paperCount} papers</info> found");
            } else {
                warning(' 🔍 Latest Search Run: File listed in latest.json not found.');
            }
        } else {
            $this->line(' 🔍 Latest Search Run: <comment>No runs executed yet.</comment>');
        }
        $this->newLine();
    }

    private function displayWikiHealth()
    {
        $papersCount = $this->countFiles('docs/wiki/papers');
        $conceptsCount = $this->countFiles('docs/wiki/concepts');
        $synthesisCount = $this->countFiles('docs/wiki/synthesis');

        $this->line(' 📚 Wiki Health:');
        table(
            ['Section', 'Page Count'],
            [
                ['Papers', $papersCount],
                ['Concepts', $conceptsCount],
                ['Synthesis', $synthesisCount],
            ]
        );
    }

    private function countFiles($path)
    {
        $fullPath = base_path($path);
        if (! File::exists($fullPath)) {
            return 0;
        }

        $files = File::files($fullPath);
        $count = 0;
        foreach ($files as $file) {
            if ($file->getExtension() === 'md' && $file->getFilename() !== 'SCHEMA.md') {
                $count++;
            }
        }

        return $count;
    }
}
