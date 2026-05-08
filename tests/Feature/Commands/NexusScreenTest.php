<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->runsDir = storage_path('runs');
    $this->screensDir = storage_path('screens');
    $this->criteriaPath = storage_path('criteria.json');

    $this->createdPaths = [];

    Carbon::setTestNow('2026-05-06 00:00:00');
});

afterEach(function () {
    foreach ($this->createdPaths as $path) {
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        } elseif (File::exists($path)) {
            File::delete($path);
        }
    }

    Carbon::setTestNow();
});

function writeRun(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!File::isDirectory($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    File::put($path, json_encode($payload, JSON_PRETTY_PRINT));
}

test('fails when criteria file is missing', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, []);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    if (File::exists($this->criteriaPath)) {
        $this->createdPaths[] = $this->criteriaPath;
        File::delete($this->criteriaPath);
    }

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(1);
});

test('writes screen file with inclusion decisions', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    $payload = [
        [
            'title' => 'Tomato instance segmentation study',
            'abstract' => 'Weakly supervised approach for tomato segmentation.',
            'year' => 2024,
        ],
        [
            'title' => 'Underwater object detection',
            'abstract' => 'Marine detection with sonar.',
            'year' => 2023,
        ],
    ];
    writeRun($runFile, $payload);
    $this->createdPaths[] = $runFile;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato', 'instance segmentation']],
        'exclude' => ['keywords' => ['underwater']],
    ], JSON_PRETTY_PRINT));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    expect(File::exists($screenFile))->toBeTrue();
    $data = json_decode(File::get($screenFile), true);

    expect($data['counts']['included'])->toBe(1);
    expect($data['counts']['excluded'])->toBe(1);
});

test('fails when run data is empty', function () {
    $runFile = "{$this->runsDir}/empty_20260506_000000.json";
    writeRun($runFile, []);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(1)
        ->expectsOutputToContain('Run file is empty');
});

test('fails when run entry is missing required keys', function () {
    $runFile = "{$this->runsDir}/bad_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'Valid paper', 'abstract' => 'An abstract', 'year' => 2024],
        ['title' => 'Missing abstract', 'year' => 2024],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(1)
        ->expectsOutputToContain('missing key: abstract');
});

test('fails when criteria is missing include or exclude keys', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'A paper', 'abstract' => 'Abstract text', 'year' => 2024],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(1)
        ->expectsOutputToContain('missing keys: exclude');
});

test('fails when include keywords are empty without flag', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'A paper', 'abstract' => 'Abstract text', 'year' => 2024],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => []],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(1)
        ->expectsOutputToContain('Include keywords are empty');
});

test('allows empty include keywords with --allow-empty-include flag', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'A paper', 'abstract' => 'Some abstract', 'year' => 2024],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => []],
        'exclude' => ['keywords' => ['banned']],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile, '--allow-empty-include' => true])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['counts']['included'])->toBe(1);
});

test('allows empty include keywords with --force flag', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'A paper', 'abstract' => 'Some abstract', 'year' => 2024],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => []],
        'exclude' => ['keywords' => ['banned']],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile, '--force' => true])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['counts']['included'])->toBe(1);
});

test('filters by year range', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'Recent tomato paper', 'abstract' => 'About tomatoes', 'year' => 2024],
        ['title' => 'Old tomato paper', 'abstract' => 'About tomatoes', 'year' => 2018],
        ['title' => 'Future tomato paper', 'abstract' => 'About tomatoes', 'year' => 2028],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato'], 'year_from' => 2020, 'year_to' => 2026],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['counts']['included'])->toBe(1);
    expect($data['counts']['excluded'])->toBe(2);
    expect($data['decisions'][1]['reasons']['year_in_range'])->toBeFalse();
    expect($data['decisions'][2]['reasons']['year_in_range'])->toBeFalse();
});

test('excludes papers with unknown year when policy is exclude', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'Tomato paper', 'abstract' => 'About tomatoes', 'year' => 2024],
        ['title' => 'Mystery tomato paper', 'abstract' => 'About tomatoes', 'year' => null],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato'], 'unknown_year' => 'exclude'],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['counts']['included'])->toBe(1);
    expect($data['counts']['excluded'])->toBe(1);
    expect($data['decisions'][1]['reasons']['year_missing'])->toBeTrue();
    expect($data['decisions'][1]['reasons']['unknown_year_policy'])->toBe('exclude');
});

test('includes papers with unknown year when policy is include', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'Mystery tomato paper', 'abstract' => 'About tomatoes', 'year' => null],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato'], 'unknown_year' => 'include'],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['counts']['included'])->toBe(1);
    expect($data['decisions'][0]['reasons']['year_missing'])->toBeTrue();
    expect($data['decisions'][0]['reasons']['year_in_range'])->toBeTrue();
});

test('dry run does not write screen file', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'Tomato study', 'abstract' => 'About tomato segmentation', 'year' => 2024],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";

    $this->artisan('nexus:screen', ['run' => $runFile, '--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('Dry run: no screen file written');

    expect(File::exists($screenFile))->toBeFalse();
});

test('records decision source as deterministic when no LLM', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'Tomato instance segmentation', 'abstract' => 'A tomato paper', 'year' => 2024],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['decisions'][0]['final_decision_source'])->toBe('deterministic');
    expect($data['decisions'][0]['deterministic_decision'])->toBeTrue();
    expect($data['counts']['deterministic'])->toBe(1);
    expect($data['counts']['llm'])->toBe(0);
    expect($data['counts']['llm_calls'])->toBe(0);
});

test('uses LLM when query has title_abstract rules and LLM is bound', function () {
    $llmCallable = function ($prompt) {
        return json_encode(['include' => true, 'reason' => 'Relevant to thesis', 'confidence' => 0.9]);
    };
    app()->bind('nexus.llm', fn () => $llmCallable);

    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        [
            'title' => 'Tomato study',
            'abstract' => 'Segmentation approach for tomatoes',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => [
                'include_title_abstract' => 'Focuses on instance segmentation for crops',
                'exclude_title_abstract' => 'Only uses synthetic data',
            ],
        ],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['decisions'][0]['final_decision_source'])->toBe('llm');
    expect($data['decisions'][0]['llm_include'])->toBeTrue();
    expect($data['decisions'][0]['llm_confidence'])->toBe(0.9);
    expect($data['decisions'][0]['llm_reason'])->toBe('Relevant to thesis');
    expect($data['counts']['llm_calls'])->toBe(1);
    expect($data['counts']['llm_failures'])->toBe(0);
});

test('falls back when LLM returns invalid JSON', function () {
    $llmCallable = function ($prompt) {
        return 'this is not json';
    };
    app()->bind('nexus.llm', fn () => $llmCallable);

    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        [
            'title' => 'Tomato study',
            'abstract' => 'Segmentation approach',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => [
                'include_title_abstract' => 'Must be about crops',
                'exclude_title_abstract' => '',
            ],
        ],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['decisions'][0]['final_decision_source'])->toBe('fallback');
    expect($data['counts']['llm_failures'])->toBe(2);
    expect($data['decisions'][0]['included'])->toBeTrue();
});

test('respects max-llm limit', function () {
    $callCount = 0;
    $llmCallable = function ($prompt) use (&$callCount) {
        $callCount++;
        return json_encode(['include' => true, 'reason' => 'ok', 'confidence' => 0.8]);
    };
    app()->bind('nexus.llm', fn () => $llmCallable);

    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        [
            'title' => 'Tomato study A',
            'abstract' => 'About tomatoes',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => ['include_title_abstract' => 'crops', 'exclude_title_abstract' => ''],
        ],
        [
            'title' => 'Tomato study B',
            'abstract' => 'About tomatoes',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => ['include_title_abstract' => 'crops', 'exclude_title_abstract' => ''],
        ],
        [
            'title' => 'Tomato study C',
            'abstract' => 'About tomatoes',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => ['include_title_abstract' => 'crops', 'exclude_title_abstract' => ''],
        ],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile, '--max-llm' => 2])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['counts']['llm_calls'])->toBe(2);
    expect($data['counts']['llm'])->toBe(2);
    expect($data['counts']['deterministic'])->toBe(1);
    expect($callCount)->toBe(2);
});

test('hard exclude cannot be overridden by LLM', function () {
    $llmCallable = function ($prompt) {
        return json_encode(['include' => true, 'reason' => 'I say include', 'confidence' => 1.0]);
    };
    app()->bind('nexus.llm', fn () => $llmCallable);

    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        [
            'title' => 'Banned tomato paper',
            'abstract' => 'About tomatoes',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => ['include_title_abstract' => 'crops', 'exclude_title_abstract' => ''],
        ],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => ['banned']],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['decisions'][0]['included'])->toBeFalse();
    expect($data['decisions'][0]['deterministic_decision'])->toBeFalse();
});

test('resolves run file from latest.json pointer', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'Tomato paper', 'abstract' => 'About tomatoes', 'year' => 2024],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    $latestPointer = "{$this->runsDir}/latest.json";
    File::put($latestPointer, json_encode([
        'file' => "storage/runs/all_20260506_000000.json",
        'run_at' => '2026-05-06T00:00:00+00:00',
    ]));
    $this->createdPaths[] = $latestPointer;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen')
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['counts']['included'])->toBe(1);
});

test('uses per-query criteria from criteria file queries mapping', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        [
            'title' => 'Tomato segmentation',
            'abstract' => 'Using deep learning for tomatoes',
            'year' => 2024,
            'query_id' => 'q1',
        ],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
        'queries' => [
            'q1' => [
                'include_title_abstract' => 'Must focus on segmentation or detection',
                'exclude_title_abstract' => 'Must not be a survey or review',
            ],
        ],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $llmCallable = function ($prompt) {
        return json_encode(['include' => true, 'reason' => 'Matches segmentation criteria', 'confidence' => 0.85]);
    };
    app()->bind('nexus.llm', fn () => $llmCallable);

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['decisions'][0]['query_id'])->toBe('q1');
    expect($data['decisions'][0]['final_decision_source'])->toBe('llm');
});

test('stores audit trail when configured', function () {
    $llmCallable = function ($prompt) {
        return json_encode(['include' => true, 'reason' => 'good', 'confidence' => 0.7]);
    };
    app()->bind('nexus.llm', fn () => $llmCallable);

    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        [
            'title' => 'Tomato study',
            'abstract' => 'About tomatoes',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => [
                'include_title_abstract' => 'crops',
                'exclude_title_abstract' => '',
            ],
        ],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
        'llm' => [
            'store_audit' => true,
            'max_audit_chars' => 50,
        ],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['decisions'][0]['llm_prompt'])->not->toBeNull();
    expect($data['decisions'][0]['llm_response'])->not->toBeNull();
    expect(mb_strlen($data['decisions'][0]['llm_prompt']))->toBeLessThanOrEqual(50);
    expect(mb_strlen($data['decisions'][0]['llm_response']))->toBeLessThanOrEqual(50);
});

test('does not store audit trail when disabled', function () {
    $llmCallable = function ($prompt) {
        return json_encode(['include' => true, 'reason' => 'good', 'confidence' => 0.7]);
    };
    app()->bind('nexus.llm', fn () => $llmCallable);

    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        [
            'title' => 'Tomato study',
            'abstract' => 'About tomatoes',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => [
                'include_title_abstract' => 'crops',
                'exclude_title_abstract' => '',
            ],
        ],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
        'llm' => ['store_audit' => false],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['decisions'][0]['llm_prompt'])->toBeNull();
    expect($data['decisions'][0]['llm_response'])->toBeNull();
});

test('excluded by keyword does not call LLM', function () {
    $llmCallable = function () {
        throw new \RuntimeException('LLM should not be called');
    };
    app()->bind('nexus.llm', fn () => $llmCallable);

    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        [
            'title' => 'Underwater survey',
            'abstract' => 'About fish',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => [
                'include_title_abstract' => 'crops',
                'exclude_title_abstract' => '',
            ],
        ],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['underwater']],
        'exclude' => ['keywords' => ['survey']],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['decisions'][0]['included'])->toBeFalse();
    expect($data['decisions'][0]['final_decision_source'])->toBe('deterministic');
    expect($data['counts']['llm_calls'])->toBe(0);
});

test('screen output includes complete counts structure', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'Tomato study', 'abstract' => 'About tomatoes', 'year' => 2024],
        ['title' => 'Other paper', 'abstract' => 'Unrelated', 'year' => 2023],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data)->toHaveKey('run_file');
    expect($data)->toHaveKey('criteria_file');
    expect($data)->toHaveKey('screened_at');
    expect($data['counts'])->toHaveKeys(['total', 'included', 'excluded', 'deterministic', 'llm', 'fallback', 'llm_calls', 'llm_failures']);
    expect($data['counts']['total'])->toBe(2);
    expect($data['decisions'])->toHaveCount(2);
});

test('uses unknown-year policy from CLI option', function () {
    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        ['title' => 'Mystery tomato', 'abstract' => 'About tomatoes', 'year' => null],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato'], 'unknown_year' => 'include'],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile, '--unknown-year' => 'exclude'])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['counts']['excluded'])->toBe(1);
    expect($data['decisions'][0]['reasons']['unknown_year_policy'])->toBe('exclude');
});

test('LLM include false results in exclusion even when deterministic passes', function () {
    $llmCallable = function ($prompt) {
        return json_encode(['include' => false, 'reason' => 'Not relevant enough', 'confidence' => 0.3]);
    };
    app()->bind('nexus.llm', fn () => $llmCallable);

    $runFile = "{$this->runsDir}/all_20260506_000000.json";
    writeRun($runFile, [
        [
            'title' => 'Tomato study',
            'abstract' => 'About tomatoes',
            'year' => 2024,
            'query_id' => 'q1',
            'query_metadata' => [
                'include_title_abstract' => 'must be deep learning',
                'exclude_title_abstract' => '',
            ],
        ],
    ]);
    $this->createdPaths[] = $runFile;
    $this->createdPaths[] = $this->runsDir;

    File::put($this->criteriaPath, json_encode([
        'include' => ['keywords' => ['tomato']],
        'exclude' => ['keywords' => []],
    ]));
    $this->createdPaths[] = $this->criteriaPath;

    $this->artisan('nexus:screen', ['run' => $runFile])
        ->assertExitCode(0);

    $screenFile = "{$this->screensDir}/all_20260506_000000.json";
    $this->createdPaths[] = $screenFile;
    $this->createdPaths[] = $this->screensDir;

    $data = json_decode(File::get($screenFile), true);
    expect($data['decisions'][0]['included'])->toBeFalse();
    expect($data['decisions'][0]['deterministic_decision'])->toBeTrue();
    expect($data['decisions'][0]['final_decision_source'])->toBe('llm');
    expect($data['decisions'][0]['llm_include'])->toBeFalse();
});

