<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nexus\Screening\Application\UseCase\ScreenCorpusCommand;
use Nexus\Screening\Application\UseCase\ScreenCorpusHandler;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningStage;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class NexusScreen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexus:screen
        {run? : path to run JSON, defaults to latest}
        {--criteria= : path to criteria JSON/YAML}
        {--dry-run : log results without writing}
        {--max-llm= : max LLM calls allowed}
        {--allow-empty-include : allow empty include keywords}
        {--unknown-year= : include|exclude|log}
        {--force : bypass safety checks}
        {--project= : project ID for database-backed core screening}
        {--include=* : inclusion criterion for database-backed core screening}
        {--exclude=* : exclusion criterion for database-backed core screening}
        {--mode=llm : llm|council for database-backed core screening}
        {--stage=title_abstract : screening stage for database-backed core screening}
        {--model= : single model for database-backed core screening}
        {--council-models= : comma-separated council model IDs}
        {--max= : maximum persisted works to screen}
        {--work-ids= : comma-separated internal work IDs}
        {--query-ids= : comma-separated search query IDs}
        {--name= : human-readable screening run name}
        {--store-prompts : persist rendered prompts in screening_votes}
        {--store-raw-responses : persist raw LLM responses in screening_votes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Screen run results using inclusion/exclusion criteria and write storage/screens/{run_id}.json.';

    public function handle(): int
    {
        if ($this->stringOption('project') !== null) {
            return $this->handleProjectScreening();
        }

        $runFile = $this->resolveRunFile();
        if ($runFile === null) {
            return self::FAILURE;
        }

        $criteriaFile = $this->resolveCriteriaFile();
        if ($criteriaFile === null) {
            return self::FAILURE;
        }

        $runDataRaw = $this->safeReadFile($runFile);
        if ($runDataRaw === null) {
            return self::FAILURE;
        }
        $runData = json_decode($runDataRaw, true);
        if (! is_array($runData)) {
            error("Run file is not valid JSON: {$runFile}");

            return self::FAILURE;
        }

        $criteriaRaw = $this->safeReadFile($criteriaFile);
        if ($criteriaRaw === null) {
            return self::FAILURE;
        }
        $criteria = json_decode($criteriaRaw, true);
        if (! is_array($criteria)) {
            error("Criteria file is not valid JSON: {$criteriaFile}");

            return self::FAILURE;
        }

        $runValidation = $this->validateRunData($runData, $runFile);
        if ($runValidation !== null) {
            error($runValidation);

            return self::FAILURE;
        }

        $criteriaValidation = $this->validateCriteria($criteria, $criteriaFile);
        if ($criteriaValidation !== null) {
            error($criteriaValidation);

            return self::FAILURE;
        }

        $runId = pathinfo($runFile, PATHINFO_FILENAME);
        $screenDir = storage_path('screens');
        if (! File::isDirectory($screenDir)) {
            File::makeDirectory($screenDir, 0755, true);
        }
        $screenFile = "{$screenDir}/{$runId}.json";

        $includeKeywords = $this->normalizeKeywords($criteria['include']['keywords'] ?? []);
        $excludeKeywords = $this->normalizeKeywords($criteria['exclude']['keywords'] ?? []);
        $yearFrom = $criteria['include']['year_from'] ?? null;
        $yearTo = $criteria['include']['year_to'] ?? null;

        $allowEmptyInclude = (bool) $this->option('allow-empty-include') || (bool) $this->option('force');
        if ($includeKeywords === [] && ! $allowEmptyInclude) {
            error('Include keywords are empty. Use --allow-empty-include or --force to proceed.');

            return self::FAILURE;
        }

        $unknownYearPolicy = $this->resolveUnknownYearPolicy($criteria);
        $maxLlmCalls = $this->resolveMaxLlmCalls();
        $llm = $this->resolveLlmCallable();

        $decisions = [];
        $includedCount = 0;
        $excludedCount = 0;
        $deterministicCount = 0;
        $llmCount = 0;
        $fallbackCount = 0;
        $llmCalls = 0;
        $llmFailures = 0;

        foreach ($runData as $work) {
            $decision = $this->decideWork(
                $work,
                $criteria,
                $includeKeywords,
                $excludeKeywords,
                $yearFrom,
                $yearTo,
                $unknownYearPolicy,
                $allowEmptyInclude,
                $llm,
                $maxLlmCalls,
                $llmCalls,
                $llmFailures
            );

            $decisions[] = $decision;
            if ($decision['included']) {
                $includedCount++;
            } else {
                $excludedCount++;
            }

            if ($decision['final_decision_source'] === 'deterministic') {
                $deterministicCount++;
            } elseif ($decision['final_decision_source'] === 'llm') {
                $llmCount++;
            } else {
                $fallbackCount++;
            }
        }

        $payload = [
            'run_file' => $this->toRelativePath($runFile),
            'criteria_file' => $this->toRelativePath($criteriaFile),
            'screened_at' => now()->toIso8601String(),
            'counts' => [
                'total' => count($decisions),
                'included' => $includedCount,
                'excluded' => $excludedCount,
                'deterministic' => $deterministicCount,
                'llm' => $llmCount,
                'fallback' => $fallbackCount,
                'llm_calls' => $llmCalls,
                'llm_failures' => $llmFailures,
            ],
            'decisions' => $decisions,
        ];

        if (! $this->option('dry-run')) {
            File::put($screenFile, json_encode($payload, JSON_PRETTY_PRINT));
            $this->line("Saved: {$screenFile}");
        } else {
            $this->line('Dry run: no screen file written.');
        }

        info("Screened {$includedCount} included, {$excludedCount} excluded.");
        $this->line("Decisions: deterministic={$deterministicCount}, llm={$llmCount}, fallback={$fallbackCount}");
        $this->line("LLM: calls={$llmCalls}, failures={$llmFailures}");

        return self::SUCCESS;
    }

    private function handleProjectScreening(): int
    {
        $projectId = $this->stringOption('project');
        if ($projectId === null) {
            error('The --project option is required.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            error('Database-backed screening does not support --dry-run yet.');

            return self::FAILURE;
        }

        try {
            $result = app(ScreenCorpusHandler::class)->handle(new ScreenCorpusCommand(
                projectId: $projectId,
                criteria: $this->coreCriteria(),
                stage: ScreeningStage::from($this->stringOption('stage') ?? ScreeningStage::TITLE_ABSTRACT->value),
                mode: $this->coreMode(),
                model: $this->stringOption('model') ?? (string) config('nexus.screening.llm.model', 'openai/gpt-4.1-mini'),
                councilModels: $this->csvOption('council-models') ?: $this->configuredCouncilModels(),
                limit: $this->intOption('max'),
                workIds: $this->csvOption('work-ids'),
                queryIds: $this->csvOption('query-ids'),
                name: $this->stringOption('name'),
                context: ['project' => $projectId],
                temperature: (float) config('nexus.screening.llm.temperature', 0),
                maxTokens: (int) config('nexus.screening.llm.max_tokens', 600),
                storePrompt: (bool) $this->option('store-prompts'),
                storeRawResponse: (bool) $this->option('store-raw-responses'),
            ));
        } catch (\Throwable $error) {
            error($error->getMessage());

            return self::FAILURE;
        }

        info('Screening complete.');
        $this->line(sprintf(
            'Run: %s | Total: %d | Include: %d | Needs review: %d | Exclude: %d | Failed: %d',
            $result->runId,
            $result->total,
            $result->included,
            $result->needsReview,
            $result->excluded,
            $result->failed,
        ));

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function coreCriteria(): ScreeningCriteria
    {
        $criteriaPath = $this->stringOption('criteria');
        if ($criteriaPath !== null) {
            return ScreeningCriteria::fromArray($this->parseCriteriaFile($this->normalizePath($criteriaPath)));
        }

        $include = $this->arrayOption('include');
        $exclude = $this->arrayOption('exclude');

        if ($include === [] && $exclude === []) {
            error('Provide --criteria, --include, or --exclude for database-backed screening.');
            throw new \InvalidArgumentException('Missing screening criteria.');
        }

        return ScreeningCriteria::fromArray([
            'include' => $include,
            'exclude' => $exclude,
        ]);
    }

    private function coreMode(): ScreeningRunMode
    {
        return match (strtolower($this->stringOption('mode') ?? 'llm')) {
            'llm', 'single', 'llm_single' => ScreeningRunMode::LLM_SINGLE,
            'council', 'llm_council' => ScreeningRunMode::LLM_COUNCIL,
            default => throw new \InvalidArgumentException('Unsupported screening mode. Use llm or council.'),
        };
    }

    /**
     * @return list<string>
     */
    private function configuredCouncilModels(): array
    {
        $models = config('nexus.screening.llm.council.models', []);

        if (! is_array($models)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($model): string => trim((string) $model),
            $models,
        )));
    }

    private function resolveRunFile(): ?string
    {
        $runArg = $this->argument('run');
        if ($runArg) {
            $runPath = $this->normalizePath($runArg);
            if (! File::exists($runPath)) {
                error("Run file not found: {$runPath}");

                return null;
            }

            return $runPath;
        }

        $latestPointer = storage_path('runs/latest.json');
        if (! File::exists($latestPointer)) {
            error('latest.json pointer not found. Run nexus:search or pass a run file path.');

            return null;
        }

        $latest = json_decode(File::get($latestPointer), true);
        $latestFile = $latest['file'] ?? null;
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

    private function resolveCriteriaFile(): ?string
    {
        $criteriaOpt = $this->option('criteria');
        $criteriaPath = $criteriaOpt ? $this->normalizePath($criteriaOpt) : storage_path('criteria.json');

        if (! File::exists($criteriaPath)) {
            error("Criteria file not found: {$criteriaPath}");
            $this->line('Create storage/criteria.json with include/exclude keywords to proceed.');

            return null;
        }

        return $criteriaPath;
    }

    private function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('#^[A-Za-z]:\\\\#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    private function normalizeKeywords(array $keywords): array
    {
        return array_values(array_filter(array_map(function ($kw) {
            if (! is_string($kw)) {
                return null;
            }
            $trimmed = $this->normalizeText($kw);

            return $trimmed === '' ? null : $trimmed;
        }, $keywords)));
    }

    private function matchesAny(string $text, array $keywords): bool
    {
        $normalized = $this->normalizeText($text);
        foreach ($keywords as $kw) {
            if ($kw === '') {
                continue;
            }
            $pattern = '/\b'.preg_quote($kw, '/').'\b/u';
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    private function yearInRange($year, $from, $to, string $unknownPolicy, ?bool &$yearMissing = null): bool
    {
        $yearMissing = ! is_int($year);
        if ($yearMissing) {
            return $unknownPolicy !== 'exclude';
        }

        if (is_int($from) && $year < $from) {
            return false;
        }

        if (is_int($to) && $year > $to) {
            return false;
        }

        return true;
    }

    private function toRelativePath(string $path): string
    {
        $base = base_path();
        if (Str::startsWith($path, $base)) {
            return ltrim(Str::after($path, $base), '\\/');
        }

        return $path;
    }

    private function safeReadFile(string $path): ?string
    {
        if (! File::exists($path)) {
            error("File not found: {$path}");

            return null;
        }

        try {
            return File::get($path);
        } catch (\Throwable $e) {
            error("Failed to read file: {$path}. {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCriteriaFile(string $path): array
    {
        if (! File::exists($path)) {
            throw new \InvalidArgumentException("Criteria file not found: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $parsed = match ($extension) {
            'yaml', 'yml' => Yaml::parseFile($path),
            default => json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR),
        };

        if (! is_array($parsed)) {
            throw new \InvalidArgumentException("Criteria file must parse to an object: {$path}");
        }

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private function arrayOption(string $name): array
    {
        $value = $this->option($name);
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $value,
        )));
    }

    /**
     * @return list<string>
     */
    private function csvOption(string $name): array
    {
        $value = $this->stringOption($name);
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $value),
        )));
    }

    private function intOption(string $name): ?int
    {
        $value = $this->stringOption($name);

        return $value === null ? null : (int) $value;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function validateRunData(array $runData, string $runFile): ?string
    {
        if ($runData === []) {
            return "Run file is empty: {$runFile}";
        }

        foreach ($runData as $index => $work) {
            if (! is_array($work)) {
                return "Run file has invalid entry at index {$index} (not an object).";
            }

            $required = ['title', 'abstract', 'year'];
            foreach ($required as $key) {
                if (! array_key_exists($key, $work)) {
                    return "Run file entry {$index} missing key: {$key}.";
                }
            }
        }

        return null;
    }

    private function validateCriteria(array $criteria, string $criteriaFile): ?string
    {
        $missing = [];
        if (! array_key_exists('include', $criteria)) {
            $missing[] = 'include';
        }
        if (! array_key_exists('exclude', $criteria)) {
            $missing[] = 'exclude';
        }

        if ($missing !== []) {
            return "Criteria file {$criteriaFile} missing keys: ".implode(', ', $missing);
        }

        return null;
    }

    private function resolveUnknownYearPolicy(array $criteria): string
    {
        $policy = $this->option('unknown-year') ?? $criteria['include']['unknown_year'] ?? 'include';
        $policy = is_string($policy) ? strtolower($policy) : 'include';
        $allowed = ['include', 'exclude', 'log'];

        if (! in_array($policy, $allowed, true)) {
            warning("Unknown unknown_year policy '{$policy}', defaulting to include.");

            return 'include';
        }

        return $policy;
    }

    private function resolveMaxLlmCalls(): int
    {
        $max = $this->option('max-llm');
        if ($max === null || $max === '') {
            return PHP_INT_MAX;
        }

        $value = (int) $max;

        return $value > 0 ? $value : 0;
    }

    private function resolveLlmCallable(): ?callable
    {
        if (app()->bound('nexus.llm')) {
            $callable = app()->make('nexus.llm');
            if (is_callable($callable)) {
                return $callable;
            }
        }

        return null;
    }

    private function decideWork(
        array $work,
        array $criteria,
        array $includeKeywords,
        array $excludeKeywords,
        $yearFrom,
        $yearTo,
        string $unknownYearPolicy,
        bool $allowEmptyInclude,
        ?callable $llm,
        int $maxLlmCalls,
        int &$llmCalls,
        int &$llmFailures
    ): array {
        $title = (string) ($work['title'] ?? '');
        $abstract = (string) ($work['abstract'] ?? '');
        $year = $work['year'] ?? null;

        $queryId = $this->resolveQueryId($work);
        $queryCriteria = $this->resolveQueryCriteria($criteria, $work, $queryId);

        if ($queryCriteria['include_keywords'] !== null) {
            $includeKeywords = $queryCriteria['include_keywords'];
        }
        if ($queryCriteria['exclude_keywords'] !== null) {
            $excludeKeywords = $queryCriteria['exclude_keywords'];
        }

        $text = "{$title} {$abstract}";

        $matchesInclude = $includeKeywords === [] && $allowEmptyInclude
            ? true
            : $this->matchesAny($text, $includeKeywords);
        $matchesExclude = $this->matchesAny($text, $excludeKeywords);

        $yearMissing = false;
        $yearOk = $this->yearInRange($year, $yearFrom, $yearTo, $unknownYearPolicy, $yearMissing);
        if ($yearMissing && $unknownYearPolicy === 'log') {
            warning("Year missing for title: {$title}");
        }

        $failedInclude = $includeKeywords !== [] && ! $matchesInclude;
        $hardExclude = $matchesExclude || ! $yearOk || $failedInclude;
        $deterministicDecision = $matchesInclude && ! $matchesExclude && $yearOk;

        $llmInclude = null;
        $llmConfidence = null;
        $llmReason = null;
        $llmPrompt = null;
        $llmResponse = null;
        $finalSource = 'deterministic';

        $hasLlmRules = $queryCriteria['include_title_abstract'] !== '' || $queryCriteria['exclude_title_abstract'] !== '';
        $inconclusive = ! $deterministicDecision && ! $hardExclude && $includeKeywords === [];

        $shouldCallLlm = ! $hardExclude
            && $llm !== null
            && $llmCalls < $maxLlmCalls
            && $hasLlmRules
            && ($deterministicDecision || $inconclusive);

        $included = $deterministicDecision;

        if ($shouldCallLlm) {
            $llmCalls++;
            $prompt = $this->buildLlmPrompt($title, $abstract, $year, $queryCriteria);
            $llmPrompt = $this->truncateAudit($prompt, $criteria);

            $response = $this->callLlm($llm, $prompt);
            $llmResponse = $this->truncateAudit($response ?? '', $criteria);

            $parsed = $this->parseLlmJson($response ?? '');
            if ($parsed === null) {
                $llmFailures++;
                $fallbackPrompt = $this->buildLlmFallbackPrompt($title, $abstract, $year, $queryCriteria);
                $llmPrompt = $this->truncateAudit($fallbackPrompt, $criteria);
                $response = $this->callLlm($llm, $fallbackPrompt);
                $llmResponse = $this->truncateAudit($response ?? '', $criteria);
                $parsed = $this->parseLlmJson($response ?? '');
            }

            if ($parsed !== null && isset($parsed['include'])) {
                $llmInclude = (bool) $parsed['include'];
                $llmConfidence = isset($parsed['confidence']) ? (float) $parsed['confidence'] : null;
                $llmReason = isset($parsed['reason']) ? (string) $parsed['reason'] : null;

                $included = $llmInclude && ! $hardExclude;
                $finalSource = 'llm';
            } else {
                $llmFailures++;
                $finalSource = 'fallback';
            }
        }

        return [
            'title' => $title,
            'year' => $year,
            'query_id' => $queryId,
            'included' => $included,
            'deterministic_decision' => $deterministicDecision,
            'final_decision_source' => $finalSource,
            'llm_include' => $llmInclude,
            'llm_confidence' => $llmConfidence,
            'llm_reason' => $llmReason,
            'llm_prompt' => $llmPrompt,
            'llm_response' => $llmResponse,
            'reasons' => [
                'matched_include' => $matchesInclude,
                'matched_exclude' => $matchesExclude,
                'year_in_range' => $yearOk,
                'year_missing' => $yearMissing,
                'unknown_year_policy' => $unknownYearPolicy,
            ],
        ];
    }

    private function resolveQueryId(array $work): ?string
    {
        if (isset($work['query_id']) && is_string($work['query_id'])) {
            return $work['query_id'];
        }

        $meta = $work['query_metadata'] ?? null;
        if (is_array($meta) && isset($meta['query_id']) && is_string($meta['query_id'])) {
            return $meta['query_id'];
        }

        return null;
    }

    private function resolveQueryCriteria(array $criteria, array $work, ?string $queryId): array
    {
        $includeRule = '';
        $excludeRule = '';

        $includeKeywords = null;
        $excludeKeywords = null;

        $workMeta = $work['query_metadata'] ?? null;
        if (is_array($workMeta)) {
            if (isset($workMeta['include_title_abstract'])) {
                $includeRule = (string) $workMeta['include_title_abstract'];
            }
            if (isset($workMeta['exclude_title_abstract'])) {
                $excludeRule = (string) $workMeta['exclude_title_abstract'];
            }
        }

        if ($queryId !== null && isset($criteria['queries'][$queryId]) && is_array($criteria['queries'][$queryId])) {
            $queryCriteria = $criteria['queries'][$queryId];
            if (isset($queryCriteria['include_title_abstract'])) {
                $includeRule = (string) $queryCriteria['include_title_abstract'];
            }
            if (isset($queryCriteria['exclude_title_abstract'])) {
                $excludeRule = (string) $queryCriteria['exclude_title_abstract'];
            }
            if (isset($queryCriteria['include']['keywords'])) {
                $includeKeywords = $this->normalizeKeywords((array) $queryCriteria['include']['keywords']);
            }
            if (isset($queryCriteria['exclude']['keywords'])) {
                $excludeKeywords = $this->normalizeKeywords((array) $queryCriteria['exclude']['keywords']);
            }
        }

        if ($includeRule === '' && isset($criteria['include_title_abstract'])) {
            $includeRule = (string) $criteria['include_title_abstract'];
        }
        if ($excludeRule === '' && isset($criteria['exclude_title_abstract'])) {
            $excludeRule = (string) $criteria['exclude_title_abstract'];
        }

        return [
            'include_title_abstract' => $includeRule,
            'exclude_title_abstract' => $excludeRule,
            'include_keywords' => $includeKeywords,
            'exclude_keywords' => $excludeKeywords,
        ];
    }

    private function buildLlmPrompt(string $title, string $abstract, $year, array $queryCriteria): string
    {
        $yearText = is_int($year) ? (string) $year : 'unknown';
        $includeRule = $queryCriteria['include_title_abstract'] ?: 'No include rule provided.';
        $excludeRule = $queryCriteria['exclude_title_abstract'] ?: 'No exclude rule provided.';

        return "You are screening a paper for inclusion.\n".
            "Title: {$title}\n".
            "Year: {$yearText}\n".
            "Abstract: {$abstract}\n\n".
            "Include rule: {$includeRule}\n".
            "Exclude rule: {$excludeRule}\n\n".
            'Return JSON only: {"include": true|false, "reason": "...", "confidence": 0-1}.';
    }

    private function buildLlmFallbackPrompt(string $title, string $abstract, $year, array $queryCriteria): string
    {
        $yearText = is_int($year) ? (string) $year : 'unknown';
        $includeRule = $queryCriteria['include_title_abstract'] ?: 'No include rule provided.';
        $excludeRule = $queryCriteria['exclude_title_abstract'] ?: 'No exclude rule provided.';

        return "Decide include=true/false based on the rules below.\n".
            "Title: {$title}\n".
            "Year: {$yearText}\n".
            "Abstract: {$abstract}\n".
            "Include rule: {$includeRule}\n".
            "Exclude rule: {$excludeRule}\n".
            'Reply with JSON only: {"include": true|false, "reason": "...", "confidence": 0-1}.';
    }

    private function callLlm(callable $llm, string $prompt): ?string
    {
        try {
            $result = $llm($prompt);
            if (is_array($result) && isset($result['content'])) {
                return (string) $result['content'];
            }

            return is_string($result) ? $result : null;
        } catch (\Throwable $e) {
            warning("LLM call failed: {$e->getMessage()}");

            return null;
        }
    }

    private function parseLlmJson(string $response): ?array
    {
        $trimmed = trim($response);
        if ($trimmed === '') {
            return null;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json = substr($trimmed, $start, $end - $start + 1);
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    private function truncateAudit(string $value, array $criteria): ?string
    {
        $store = $criteria['llm']['store_audit'] ?? false;
        if (! $store) {
            return null;
        }

        $maxLen = (int) ($criteria['llm']['max_audit_chars'] ?? 2000);
        if ($maxLen <= 0) {
            $maxLen = 2000;
        }

        return mb_substr($value, 0, $maxLen);
    }

    private function normalizeText(string $text): string
    {
        $lower = mb_strtolower($text);
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower);
        $collapsed = preg_replace('/\s+/', ' ', $normalized ?? '');

        return trim($collapsed ?? '');
    }
}
