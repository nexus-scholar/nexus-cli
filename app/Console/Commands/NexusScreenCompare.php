<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
        {--json : output JSON}
        {--no-rows : omit per-work rows from result}';

    protected $description = 'Compare two persisted Nexus Scholar screening runs.';

    public function handle(CompareScreeningRunsHandler $handler): int
    {
        $projectId = $this->stringOption('project');
        $baselineRunId = $this->stringOption('baseline-run');
        $candidateRunId = $this->stringOption('candidate-run');

        if ($projectId === null || $baselineRunId === null || $candidateRunId === null) {
            error('Provide --project, --baseline-run, and --candidate-run.');

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

    private function stageOption(): ?ScreeningStage
    {
        $stage = $this->stringOption('stage');

        return $stage === null ? null : ScreeningStage::from($stage);
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
}
