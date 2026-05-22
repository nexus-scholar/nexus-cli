<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nexus\Screening\Application\UseCase\AdjudicateScreeningDecisionsCommand;
use Nexus\Screening\Application\UseCase\AdjudicateScreeningDecisionsHandler;
use Nexus\Screening\Application\UseCase\HumanAdjudicationInput;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningStage;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class NexusScreenAdjudicate extends Command
{
    protected $signature = 'nexus:screen-adjudicate
        {--project= : project ID}
        {--actor= : reviewer/user ID}
        {--file= : YAML/JSON adjudication file}
        {--run= : existing or desired human screening run ID}
        {--stage= : screening stage override}
        {--criteria-hash= : criteria hash override}
        {--name= : human-readable adjudication run name}
        {--example : print an example YAML adjudication file and exit}';

    protected $description = 'Record human adjudication decisions for a locked Nexus Scholar project.';

    public function handle(): int
    {
        if ((bool) $this->option('example')) {
            foreach (explode("\n", $this->exampleYaml()) as $line) {
                $this->line(rtrim($line, "\r"));
            }

            return self::SUCCESS;
        }

        $projectId = $this->stringOption('project');
        $actorId = $this->stringOption('actor');
        $file = $this->stringOption('file');

        if ($projectId === null || $actorId === null || $file === null) {
            error('Provide --project, --actor, and --file. Example: php artisan nexus:screen-adjudicate --project=tomatomap_label_efficiency --actor=reviewer-1 --file=storage/adjudication.yml');

            return self::FAILURE;
        }

        try {
            $payload = $this->readPayload($this->normalizePath($file));
            $stage = $this->stageFrom($this->stringOption('stage') ?? $this->payloadString($payload, 'stage') ?? ScreeningStage::TITLE_ABSTRACT->value);
            $criteriaHash = $this->stringOption('criteria-hash') ?? $this->payloadString($payload, 'criteria_hash');

            if ($criteriaHash === null) {
                error('Provide --criteria-hash or criteria_hash in the adjudication file.');

                return self::FAILURE;
            }

            $decisions = $this->decisionInputs($payload);

            $handler = app(AdjudicateScreeningDecisionsHandler::class);
            $result = $handler->handle(new AdjudicateScreeningDecisionsCommand(
                projectId: $projectId,
                actorId: $actorId,
                stage: $stage,
                criteriaHash: $criteriaHash,
                decisions: $decisions,
                screeningRunId: $this->stringOption('run') ?? $this->payloadString($payload, 'run_id'),
                runName: $this->stringOption('name') ?? $this->payloadString($payload, 'run_name'),
            ));
        } catch (\Throwable $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        info('Adjudication complete.');
        $this->line(sprintf(
            'Run: %s | Total: %d | Include: %d | Needs review: %d | Exclude: %d',
            $result->runId,
            $result->total,
            $result->included,
            $result->needsReview,
            $result->excluded,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(string $path): array
    {
        if (! File::exists($path)) {
            throw new \InvalidArgumentException("Adjudication file not found: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $payload = match ($extension) {
            'yaml', 'yml' => Yaml::parseFile($path),
            'json' => json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR),
            default => throw new \InvalidArgumentException('Adjudication file must be JSON or YAML. Use --example to print the expected shape.'),
        };

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Adjudication file must parse to an object.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return non-empty-list<HumanAdjudicationInput>
     */
    private function decisionInputs(array $payload): array
    {
        $rows = $payload['decisions'] ?? null;
        if (! is_array($rows) || $rows === []) {
            throw new \InvalidArgumentException('Adjudication file requires a non-empty decisions array.');
        }

        $decisions = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException("Decision row {$index} must be an object.");
            }

            $workId = $this->rowString($row, 'work_id') ?? $this->rowString($row, 'workId');
            $decision = $this->rowString($row, 'decision');
            $reason = $this->rowString($row, 'reason');

            if ($workId === null || $decision === null || $reason === null) {
                throw new \InvalidArgumentException("Decision row {$index} requires work_id, decision, and reason.");
            }

            $confidence = $this->rowFloat($row, 'confidence');
            if ($confidence !== null && ($confidence < 0.0 || $confidence > 1.0)) {
                throw new \InvalidArgumentException("Decision row {$index} confidence must be between 0 and 1.");
            }

            $decisions[] = new HumanAdjudicationInput(
                workId: $workId,
                decision: $this->decisionFrom($decision, $index),
                reason: $reason,
                evidence: $this->rowList($row, 'evidence'),
                uncertainty: $this->rowList($row, 'uncertainty'),
                exclusionBasis: $this->rowList($row, 'exclusion_basis'),
                sourceDecisionIds: $this->rowList($row, 'source_decision_ids'),
                confidence: $confidence ?? 1.0,
            );
        }

        return $decisions;
    }

    private function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadString(array $payload, string $key): ?string
    {
        return isset($payload[$key]) && is_scalar($payload[$key]) && trim((string) $payload[$key]) !== ''
            ? trim((string) $payload[$key])
            : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowString(array $row, string $key): ?string
    {
        return isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== ''
            ? trim((string) $row[$key])
            : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function rowList(array $row, string $key): array
    {
        $value = $row[$key] ?? [];
        $items = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $items,
        ), static fn (string $item): bool => $item !== ''));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowFloat(array $row, string $key): ?float
    {
        return isset($row[$key]) && is_numeric($row[$key]) ? (float) $row[$key] : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function stageFrom(string $stage): ScreeningStage
    {
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

    private function decisionFrom(string $decision, int $index): ScreeningDecision
    {
        foreach (ScreeningDecision::cases() as $case) {
            if ($case->value === $decision) {
                return $case;
            }
        }

        throw new \InvalidArgumentException(sprintf(
            'Decision row %d has invalid decision "%s". Allowed decisions: %s.',
            $index,
            $decision,
            implode(', ', array_map(static fn (ScreeningDecision $case): string => $case->value, ScreeningDecision::cases())),
        ));
    }

    private function exampleYaml(): string
    {
        return <<<'YAML'
stage: title_abstract
criteria_hash: tomato-label-efficiency-v1
run_id: human-adjudication-2026-05-22
run_name: TomatoMAP human adjudication
decisions:
  - work_id: 00000000-0000-0000-0000-000000000001
    decision: include
    reason: The title and abstract directly study tomato instance segmentation with label-efficient learning.
    evidence:
      - tomato instance segmentation
      - limited annotation budget
    exclusion_basis: []
    confidence: 1.0
    source_decision_ids:
      - previous-screening-decision-id
YAML;
    }
}
