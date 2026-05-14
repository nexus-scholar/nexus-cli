<?php

namespace App\Search;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Nexus\Search\Application\Aggregator\AggregatedResult;

final class SearchConsoleRenderer
{
    public function renderStart(Command $command, SearchPlan $plan, int $selectedCount, string $projectId): void
    {
        $command->line(sprintf(
            'Running %d quer%s from %s (project: %s)',
            $selectedCount,
            $selectedCount === 1 ? 'y' : 'ies',
            basename($plan->sourcePath),
            $projectId
        ));
        $command->newLine();
    }

    public function renderQueryRun(Command $command, SearchQueryRun $run, int $current, int $total): void
    {
        $command->line(sprintf(
            '<fg=cyan>[%d/%d]</> <options=bold>%s</> (%s)',
            $current,
            $total,
            $run->query->id,
            $run->query->label
        ));
        $command->line("Query: {$run->query->query}");
        $command->line('  Core query ID: '.$run->coreQueryId);
        $command->line('  Completed in '.$run->elapsedMs.'ms'.($run->result->fromCache ? ' (cached)' : ''));

        $this->renderProviderStats($command, $run->result->providerStats);
        $this->renderDedupSummary($command, $run->result);

        $command->line("  Saved to: {$run->file->absolute}");
        $command->newLine();
    }

    public function renderFinished(Command $command, SearchRunReport $report): void
    {
        if ($report->globalFile !== null) {
            $command->line("  Saved global deduped master to: {$report->globalFile->absolute}");
            $command->line('  Note: all_*.json is deduplicated with nexus-scholar/core CorpusSlice rules.');
        }

        $command->info('Updated storage/runs/latest.json pointer.');
    }

    private function renderProviderStats(Command $command, array $providerStats): void
    {
        if ($providerStats === []) {
            $command->line('  Providers: none (all skipped or disabled).');

            return;
        }

        $rows = [];
        $failures = [];
        foreach ($providerStats as $stat) {
            $message = $stat->skipReason ?? '—';
            $rows[] = [
                $stat->alias,
                $stat->resultCount,
                $stat->latencyMs.'ms',
                $stat->skipReason === null ? 'OK' : 'Failed',
                Str::limit($message, 80),
            ];

            if ($stat->skipReason !== null) {
                $failures[] = ['provider' => $stat->alias, 'message' => $message];
            }
        }

        $command->table(['Provider', 'Results', 'Latency', 'Status', 'Message'], $rows);

        if ($failures !== []) {
            $command->line('<fg=yellow>Provider failures:</>');
            foreach ($failures as $failure) {
                $command->line(sprintf('  - <fg=red>%s</>: %s', $failure['provider'], $failure['message']));
            }
        }
    }

    private function renderDedupSummary(Command $command, AggregatedResult $result): void
    {
        $uniqueCount = $result->corpus->count();
        $duplicates = max(0, $result->totalRaw - $uniqueCount);
        $keptRate = $result->totalRaw > 0 ? round(($uniqueCount / $result->totalRaw) * 100, 1) : 0.0;

        $command->line(sprintf(
            '  Raw: <comment>%d</comment>  →  Unique: <comment>%d</comment>  (dupes: %d, %s%% kept)',
            $result->totalRaw,
            $uniqueCount,
            $duplicates,
            $keptRate
        ));
    }
}
