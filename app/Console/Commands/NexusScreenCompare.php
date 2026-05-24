<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Laravel\Model\ScreeningRunModel;
use Nexus\Screening\Application\UseCase\CompareScreeningRunsCommand;
use Nexus\Screening\Application\UseCase\CompareScreeningRunsHandler;
use Nexus\Screening\Application\UseCase\ScreeningRunComparisonResult;
use Nexus\Screening\Domain\ScreeningStage;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class NexusScreenCompare extends Command
{
    protected $signature = 'nexus:screen-compare
        {--project= : project ID}
        {--baseline-run= : baseline screening run ID}
        {--candidate-run= : candidate screening run ID}
        {--stage= : optional stage filter}
        {--list-runs : list recent screening runs for the project instead of comparing two runs}
        {--limit=10 : number of runs to list with --list-runs}
        {--json : output JSON}
        {--no-rows : omit per-work rows from result}';

    protected $description = 'Compare two persisted Nexus Scholar screening runs.';

    public function handle(CompareScreeningRunsHandler $handler): int
    {
        $projectId = $this->stringOption('project');
        $baselineRunId = $this->stringOption('baseline-run');
        $candidateRunId = $this->stringOption('candidate-run');

        if ((bool) $this->option('list-runs')) {
            return $this->listRuns($projectId);
        }

        if ($projectId === null || $baselineRunId === null || $candidateRunId === null) {
            error('Provide --project, --baseline-run, and --candidate-run. Use --list-runs to discover run IDs for a project.');

            return self::FAILURE;
        }

        try {
            $result = $handler->handle(new CompareScreeningRunsCommand(
                projectId: $projectId,
                baselineRunId: $baselineRunId,
                candidateRunId: $candidateRunId,
                stage: $this->stageOption(),
                includeRows: ! (bool) $this->option('no-rows'),
            ));
        } catch (\Throwable $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($this->toArray($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        info('Screening comparison complete.');
        $this->line(sprintf(
            'Project: %s | Baseline: %s (%s/%s) | Candidate: %s (%s/%s)',
            $result->projectId,
            $result->baselineRun['id'] ?? 'unknown',
            $result->baselineRun['mode'] ?? 'unknown',
            $result->baselineRun['stage'] ?? 'unknown',
            $result->candidateRun['id'] ?? 'unknown',
            $result->candidateRun['mode'] ?? 'unknown',
            $result->candidateRun['stage'] ?? 'unknown',
        ));
        $this->line(sprintf(
            'Comparable: %d | Agreement: %d (%.1f%%) | Disagreement: %d (%.1f%%)',
            $result->comparableTotal,
            $result->agreementCount,
            $result->agreementRate * 100,
            $result->disagreementCount,
            $result->disagreementRate * 100,
        ));
        $this->line(sprintf(
            'Missing in baseline: %d | Missing in candidate: %d',
            count($result->missingInBaseline),
            count($result->missingInCandidate),
        ));

        foreach ($result->transitionCounts as $from => $targets) {
            foreach ($targets as $to => $count) {
                $this->line("{$from} -> {$to}: {$count}");
            }
        }

        if ($result->referenceRunId !== null) {
            $this->line("Reference run: {$result->referenceRunId}");
        }

        return self::SUCCESS;
    }

    private function listRuns(?string $projectId): int
    {
        if ($projectId === null) {
            error('Provide --project when using --list-runs.');

            return self::FAILURE;
        }

        try {
            $limit = $this->intOption('limit', min: 1, max: 100);
        } catch (\InvalidArgumentException $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        $runs = ScreeningRunModel::query()
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'stage',
                'mode',
                'status',
                'name',
                'criteria_hash',
                'counts',
                'created_at',
                'completed_at',
            ]);

        $rows = $runs
            ->map(fn (ScreeningRunModel $run): array => $this->runListRow($run))
            ->values()
            ->all();

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'project_id' => $projectId,
                'limit' => $limit,
                'runs' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rows === []) {
            info("No screening runs found for project {$projectId}.");

            return self::SUCCESS;
        }

        $this->table(
            ['Run ID', 'Stage', 'Mode', 'Status', 'Name', 'Counts', 'Created'],
            array_map(static fn (array $row): array => [
                $row['id'],
                $row['stage'],
                $row['mode'],
                $row['status'],
                $row['name'] ?? '',
                $row['counts_summary'],
                $row['created_at'] ?? '',
            ], $rows),
        );

        return self::SUCCESS;
    }

    private function stageOption(): ?ScreeningStage
    {
        $stage = $this->stringOption('stage');

        if ($stage === null) {
            return null;
        }

        foreach (ScreeningStage::cases() as $case) {
            if ($case->value === $stage) {
                return $case;
            }
        }

        throw new \InvalidArgumentException(sprintf(
            'Invalid screening stage "%s". Allowed stages: %s.',
            $stage,
            implode(', ', array_map(static fn (ScreeningStage $case): string => $case->value, ScreeningStage::cases())),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(ScreeningRunComparisonResult $result): array
    {
        return [
            'project_id' => $result->projectId,
            'baseline_run' => $result->baselineRun,
            'candidate_run' => $result->candidateRun,
            'comparable_total' => $result->comparableTotal,
            'agreement_count' => $result->agreementCount,
            'disagreement_count' => $result->disagreementCount,
            'agreement_rate' => $result->agreementRate,
            'disagreement_rate' => $result->disagreementRate,
            'transition_counts' => $result->transitionCounts,
            'missing_in_baseline' => $result->missingInBaseline,
            'missing_in_candidate' => $result->missingInCandidate,
            'reference_run_id' => $result->referenceRunId,
            'rows' => array_map(static fn ($row): array => [
                'work_id' => $row->workId,
                'baseline_decision' => $row->baselineDecision,
                'candidate_decision' => $row->candidateDecision,
                'changed' => $row->changed,
                'baseline' => $row->baseline,
                'candidate' => $row->candidate,
            ], $result->rows),
        ];
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function intOption(string $name, int $min, int $max): int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT);

        if (! is_int($value) || $value < $min || $value > $max) {
            throw new \InvalidArgumentException("--{$name} must be an integer between {$min} and {$max}.");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function runListRow(ScreeningRunModel $run): array
    {
        $counts = is_array($run->counts) ? $run->counts : [];

        return [
            'id' => (string) $run->id,
            'stage' => (string) $run->stage,
            'mode' => (string) $run->mode,
            'status' => (string) $run->status,
            'name' => $run->name === null ? null : (string) $run->name,
            'criteria_hash' => $run->criteria_hash === null ? null : (string) $run->criteria_hash,
            'counts' => $counts,
            'counts_summary' => $this->countsSummary($counts),
            'created_at' => $run->created_at?->toISOString(),
            'completed_at' => $run->completed_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $counts
     */
    private function countsSummary(array $counts): string
    {
        if ($counts === []) {
            return '';
        }

        $parts = [];
        foreach ($counts as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = "{$key}:{$value}";
            }
        }

        return implode(' ', $parts);
    }
}
