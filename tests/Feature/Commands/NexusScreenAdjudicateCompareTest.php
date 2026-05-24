<?php

namespace Tests\Feature\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Application\UseCase\AdjudicateScreeningDecisionsHandler;
use Nexus\Screening\Application\UseCase\CompareScreeningRunsHandler;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningRunStatus;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVerdict;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;

afterEach(function (): void {
    File::delete(storage_path('adjudication-test.yml'));
    Carbon::setTestNow();
});

test('screen adjudicate command records human decisions through core handler', function (): void {
    $runs = cliAdjudicationRuns();
    $decisions = cliAdjudicationDecisions();

    app()->instance(AdjudicateScreeningDecisionsHandler::class, new AdjudicateScreeningDecisionsHandler(
        $runs,
        $decisions,
        new CorpusLockPolicy(
            cliLockPort(['project-1' => true]),
            cliMembershipPort(),
        ),
    ));

    File::put(storage_path('adjudication-test.yml'), <<<'YAML'
stage: title_abstract
criteria_hash: criteria-hash
run_id: human-run-1
run_name: Reviewer adjudication
decisions:
  - work_id: work-1
    decision: include
    reason: Direct tomato instance segmentation match.
    evidence:
      - tomato instance segmentation
YAML);

    $this->artisan('nexus:screen-adjudicate', [
        '--project' => 'project-1',
        '--actor' => 'reviewer-1',
        '--file' => storage_path('adjudication-test.yml'),
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Adjudication complete.')
        ->expectsOutputToContain('Run: human-run-1 | Total: 1 | Include: 1 | Needs review: 0 | Exclude: 0');

    expect($runs->started['human-run-1']->mode)->toBe(ScreeningRunMode::HUMAN)
        ->and($decisions->recorded[0]->source)->toBe('human')
        ->and($decisions->recorded[0]->decidedBy)->toBe('reviewer-1');
});

test('screen adjudicate command prints an example file', function (): void {
    $this->artisan('nexus:screen-adjudicate', [
        '--example' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutput('stage: title_abstract')
        ->expectsOutput('criteria_hash: tomato-label-efficiency-v1')
        ->expectsOutput('run_id: human-adjudication-2026-05-22')
        ->expectsOutput('run_name: TomatoMAP human adjudication')
        ->expectsOutput('decisions:')
        ->expectsOutput('  - work_id: 00000000-0000-0000-0000-000000000001')
        ->expectsOutput('    decision: include')
        ->expectsOutput('    reason: The title and abstract directly study tomato instance segmentation with label-efficient learning.')
        ->expectsOutput('    evidence:')
        ->expectsOutput('      - tomato instance segmentation')
        ->expectsOutput('      - limited annotation budget')
        ->expectsOutput('    exclusion_basis: []')
        ->expectsOutput('    confidence: 1.0')
        ->expectsOutput('    source_decision_ids:')
        ->expectsOutput('      - previous-screening-decision-id');
});

test('screen adjudicate command reports invalid decisions with row context', function (): void {
    File::put(storage_path('adjudication-test.yml'), <<<'YAML'
stage: title_abstract
criteria_hash: criteria-hash
decisions:
  - work_id: work-1
    decision: maybe
    reason: The reviewer is unsure.
YAML);

    $this->artisan('nexus:screen-adjudicate', [
        '--project' => 'project-1',
        '--actor' => 'reviewer-1',
        '--file' => storage_path('adjudication-test.yml'),
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Decision row 0 has invalid decision "maybe". Allowed decisions: include, needs_review, exclude.');
});

test('screen compare command prints transition summary from core handler', function (): void {
    app()->instance(CompareScreeningRunsHandler::class, new CompareScreeningRunsHandler(
        cliCompareRuns([
            cliRun('baseline-run', ScreeningRunMode::RULES),
            cliRun('human-run', ScreeningRunMode::HUMAN),
        ]),
        cliCompareDecisions([
            cliVerdict('d1', 'baseline-run', 'work-1', ScreeningDecision::INCLUDE, 'rules'),
            cliVerdict('d2', 'baseline-run', 'work-2', ScreeningDecision::EXCLUDE, 'rules'),
            cliVerdict('d3', 'human-run', 'work-1', ScreeningDecision::INCLUDE, 'human'),
            cliVerdict('d4', 'human-run', 'work-2', ScreeningDecision::INCLUDE, 'human'),
        ]),
    ));

    $this->artisan('nexus:screen-compare', [
        '--project' => 'project-1',
        '--baseline-run' => 'baseline-run',
        '--candidate-run' => 'human-run',
        '--stage' => 'title_abstract',
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Screening comparison complete.')
        ->expectsOutputToContain('Comparable: 2 | Agreement: 1 (50.0%) | Disagreement: 1 (50.0%)')
        ->expectsOutputToContain('exclude -> include: 1')
        ->expectsOutputToContain('Reference run: human-run');
});

test('screen compare command lists recent persisted runs for a project', function (): void {
    $this->artisan('migrate:fresh')->run();
    Carbon::setTestNow('2026-05-24 10:00:00');

    DB::table('projects')->insert([
        'id' => 'project-1',
        'name' => 'TomatoMAP label efficiency',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('screening_runs')->insert([
        [
            'id' => 'rules-run',
            'project_id' => 'project-1',
            'stage' => ScreeningStage::TITLE_ABSTRACT->value,
            'name' => 'Deterministic screen',
            'mode' => ScreeningRunMode::RULES->value,
            'status' => ScreeningRunStatus::COMPLETED->value,
            'criteria_hash' => 'criteria-hash',
            'criteria' => json_encode(['include' => ['tomato']]),
            'counts' => json_encode(['include' => 3, 'exclude' => 1]),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ],
        [
            'id' => 'human-run',
            'project_id' => 'project-1',
            'stage' => ScreeningStage::TITLE_ABSTRACT->value,
            'name' => 'Human adjudication',
            'mode' => ScreeningRunMode::HUMAN->value,
            'status' => ScreeningRunStatus::COMPLETED->value,
            'criteria_hash' => 'criteria-hash',
            'criteria' => json_encode(['include' => ['tomato']]),
            'counts' => json_encode(['include' => 4, 'exclude' => 0]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $exitCode = Artisan::call('nexus:screen-compare', [
        '--project' => 'project-1',
        '--list-runs' => true,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())
        ->toContain('"project_id": "project-1"')
        ->toContain('"id": "human-run"')
        ->toContain('"mode": "human"')
        ->toContain('"counts_summary": "include:4 exclude:0"')
        ->toContain('"id": "rules-run"');
});

test('screen compare command reports invalid stage values clearly', function (): void {
    $this->artisan('nexus:screen-compare', [
        '--project' => 'project-1',
        '--baseline-run' => 'baseline-run',
        '--candidate-run' => 'human-run',
        '--stage' => 'abstract_only',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Invalid screening stage "abstract_only". Allowed stages: title_abstract, full_text, human_adjudication.');
});

function cliLockPort(array $locks): ProjectLockPort
{
    return new class($locks) implements ProjectLockPort
    {
        public function __construct(private readonly array $locks) {}

        public function isLocked(string $projectId): bool
        {
            return $this->locks[$projectId] ?? false;
        }
    };
}

function cliMembershipPort(): ProjectWorkMembershipPort
{
    return new class implements ProjectWorkMembershipPort
    {
        public function missingWorkIds(string $projectId, array $workIds): array
        {
            return [];
        }
    };
}

function cliAdjudicationRuns(): object
{
    return new class implements ScreeningRunRepositoryPort
    {
        public array $started = [];

        public array $completed = [];

        public function get(string $screeningRunId): ?ScreeningRun
        {
            return $this->started[$screeningRunId] ?? null;
        }

        public function start(ScreeningRun $run): void
        {
            $this->started[$run->id] = $run;
        }

        public function complete(string $screeningRunId, array $counts): void
        {
            $this->completed[$screeningRunId] = $counts;
        }

        public function fail(string $screeningRunId, string $message): void {}
    };
}

function cliAdjudicationDecisions(): object
{
    return new class implements ScreeningDecisionRepositoryPort
    {
        public array $recorded = [];

        public function record(ScreeningVerdict $verdict): void
        {
            $this->recorded[] = $verdict;
        }

        public function latestForWork(string $projectId, string $workId, ScreeningStage $stage): ?ScreeningVerdict
        {
            return null;
        }

        public function forRun(string $screeningRunId): array
        {
            return array_values(array_filter(
                $this->recorded,
                static fn (ScreeningVerdict $verdict): bool => $verdict->screeningRunId === $screeningRunId,
            ));
        }
    };
}

function cliRun(string $id, ScreeningRunMode $mode): ScreeningRun
{
    return new ScreeningRun(
        id: $id,
        projectId: 'project-1',
        stage: ScreeningStage::TITLE_ABSTRACT,
        mode: $mode,
        status: ScreeningRunStatus::COMPLETED,
        criteria: ScreeningCriteria::fromArray(['include' => ['tomato']]),
    );
}

function cliVerdict(
    string $id,
    string $runId,
    string $workId,
    ScreeningDecision $decision,
    string $source,
): ScreeningVerdict {
    return new ScreeningVerdict(
        id: $id,
        screeningRunId: $runId,
        projectId: 'project-1',
        workId: $workId,
        stage: ScreeningStage::TITLE_ABSTRACT,
        decision: $decision,
        confidence: 0.9,
        source: $source,
        rationale: new ScreeningRationale("{$source} decision"),
    );
}

function cliCompareRuns(array $runs): ScreeningRunRepositoryPort
{
    return new class($runs) implements ScreeningRunRepositoryPort
    {
        private array $runs = [];

        public function __construct(array $runs)
        {
            foreach ($runs as $run) {
                $this->runs[$run->id] = $run;
            }
        }

        public function get(string $screeningRunId): ?ScreeningRun
        {
            return $this->runs[$screeningRunId] ?? null;
        }

        public function start(ScreeningRun $run): void {}

        public function complete(string $screeningRunId, array $counts): void {}

        public function fail(string $screeningRunId, string $message): void {}
    };
}

function cliCompareDecisions(array $verdicts): ScreeningDecisionRepositoryPort
{
    return new class($verdicts) implements ScreeningDecisionRepositoryPort
    {
        public function __construct(private readonly array $verdicts) {}

        public function record(ScreeningVerdict $verdict): void {}

        public function latestForWork(string $projectId, string $workId, ScreeningStage $stage): ?ScreeningVerdict
        {
            return null;
        }

        public function forRun(string $screeningRunId): array
        {
            return array_values(array_filter(
                $this->verdicts,
                static fn (ScreeningVerdict $verdict): bool => $verdict->screeningRunId === $screeningRunId,
            ));
        }
    };
}
