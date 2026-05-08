<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->basePath = base_path('docs/wiki');
    $this->templatePath = base_path('docs/wiki-schema.md');
});

afterEach(function () {
    if (File::isDirectory($this->basePath)) {
        File::deleteDirectory($this->basePath);
    }
});

test('preserves existing content in subdirectories', function () {
    if (!File::isDirectory("{$this->basePath}/papers")) {
        File::makeDirectory("{$this->basePath}/papers", 0755, true);
    }
    $testPaper = "# Test Paper\nContent here";
    File::put("{$this->basePath}/papers/test-paper-2024.md", $testPaper);

    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $this->assertTrue(File::exists("{$this->basePath}/papers/test-paper-2024.md"));
    $this->assertEquals($testPaper, File::get("{$this->basePath}/papers/test-paper-2024.md"));
});

test('index file has valid markdown format', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $content = File::get("{$this->basePath}/index.md");

    $this->assertStringContainsString('#', $content);
    $this->assertStringNotContainsString("\t", $content);
});

test('log file has valid table structure', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $content = File::get("{$this->basePath}/log.md");
    $lines = explode("\n", $content);

    $tableHeaderFound = false;
    foreach ($lines as $line) {
        if (strpos($line, '| Date | Action | Details |') !== false) {
            $tableHeaderFound = true;
            break;
        }
    }

    $this->assertTrue($tableHeaderFound, 'Table header not found in log.md');
});

test('created files are readable', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $files = [
        "{$this->basePath}/SCHEMA.md",
        "{$this->basePath}/index.md",
        "{$this->basePath}/log.md",
    ];

    foreach ($files as $file) {
        $this->assertTrue(is_readable($file), "File {$file} is not readable");
        $this->assertNotEmpty(File::get($file), "File {$file} is empty");
    }
});

test('double run produces identical results', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);
    $firstRunIndexContent = File::get("{$this->basePath}/index.md");
    $firstRunSchemaContent = File::get("{$this->basePath}/SCHEMA.md");

    File::deleteDirectory($this->basePath);

    $this->artisan('nexus:wiki-init')->assertExitCode(0);
    $secondRunIndexContent = File::get("{$this->basePath}/index.md");
    $secondRunSchemaContent = File::get("{$this->basePath}/SCHEMA.md");

    $this->assertEquals($firstRunIndexContent, $secondRunIndexContent);
    $this->assertEquals($firstRunSchemaContent, $secondRunSchemaContent);
});

test('schema content matches template exactly', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $templateContent = File::get($this->templatePath);
    $schemaContent = File::get("{$this->basePath}/SCHEMA.md");

    $this->assertEquals($templateContent, $schemaContent);
});

test('subdirectory types are correct', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $dirs = [
        "{$this->basePath}/papers",
        "{$this->basePath}/concepts",
        "{$this->basePath}/synthesis",
    ];

    foreach ($dirs as $dir) {
        $this->assertTrue(is_dir($dir), "{$dir} is not a directory");
    }
});

test('log file date format is valid', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $content = File::get("{$this->basePath}/log.md");
    $today = date('Y-m-d');

    $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $content);
    $this->assertStringContainsString($today, $content);
});

test('handles complex existing tree', function () {
    if (!File::isDirectory("{$this->basePath}/papers")) {
        File::makeDirectory("{$this->basePath}/papers", 0755, true);
    }
    if (!File::isDirectory("{$this->basePath}/concepts")) {
        File::makeDirectory("{$this->basePath}/concepts", 0755, true);
    }
    File::put("{$this->basePath}/papers/paper1.md", '# Paper 1');
    File::put("{$this->basePath}/papers/paper2.md", '# Paper 2');
    File::put("{$this->basePath}/concepts/concept1.md", '# Concept 1');

    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $this->assertTrue(File::exists("{$this->basePath}/papers/paper1.md"));
    $this->assertTrue(File::exists("{$this->basePath}/papers/paper2.md"));
    $this->assertTrue(File::exists("{$this->basePath}/concepts/concept1.md"));

    $this->assertTrue(File::exists("{$this->basePath}/index.md"));
    $this->assertTrue(File::exists("{$this->basePath}/log.md"));
});

test('output is consistent across runs', function () {
    $this->artisan('nexus:wiki-init')
        ->expectsOutput("Created directory: {$this->basePath}")
        ->assertExitCode(0);

    File::deleteDirectory($this->basePath);

    $this->artisan('nexus:wiki-init')
        ->expectsOutput("Created directory: {$this->basePath}")
        ->assertExitCode(0);
});

test('all required seed content in index', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $content = File::get("{$this->basePath}/index.md");

    $requiredSections = [
        '# Research Wiki Index',
        'Core Concepts',
        'Recent Papers',
        'Synthesis Reports',
    ];

    foreach ($requiredSections as $section) {
        $this->assertStringContainsString($section, $content, "Section '{$section}' not found in index");
    }
});

test('all required seed content in log', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $content = File::get("{$this->basePath}/log.md");

    $requiredContent = [
        '# Wiki Activity Log',
        '| Date | Action | Details |',
        'Init',
    ];

    foreach ($requiredContent as $part) {
        $this->assertStringContainsString($part, $content, "Content '{$part}' not found in log");
    }
});

test('base directory has files and subdirectories', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $baseDirContents = File::files($this->basePath);
    $baseDirSubdirs = File::directories($this->basePath);

    $this->assertGreaterThan(0, count($baseDirContents), 'Base directory should contain files');
    $this->assertGreaterThan(0, count($baseDirSubdirs), 'Base directory should contain subdirectories');
});

test('gitkeep files have expected behavior', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $gitkeepFiles = [
        "{$this->basePath}/papers/.gitkeep",
        "{$this->basePath}/concepts/.gitkeep",
        "{$this->basePath}/synthesis/.gitkeep",
    ];

    foreach ($gitkeepFiles as $file) {
        $this->assertTrue(File::exists($file));
        $this->assertEquals('', File::get($file));
    }
});

test('respects existing directory structure', function () {
    if (!File::isDirectory("{$this->basePath}/papers")) {
        File::makeDirectory("{$this->basePath}/papers", 0755, true);
    }

    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $this->assertTrue(File::isDirectory("{$this->basePath}/papers"));
});

test('file content is valid utf8', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $files = [
        "{$this->basePath}/SCHEMA.md",
        "{$this->basePath}/index.md",
        "{$this->basePath}/log.md",
    ];

    foreach ($files as $file) {
        $content = File::get($file);
        $this->assertTrue(mb_check_encoding($content, 'UTF-8'), "File {$file} is not valid UTF-8");
    }
});

test('schema file has expected header', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $content = File::get("{$this->basePath}/SCHEMA.md");
    $lines = preg_split('/\r?\n/', $content);

    $this->assertStringContainsString('Wiki Schema', $lines[0]);
});

test('all created items are in expected locations', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $structure = [
        'subdirs' => [
            "{$this->basePath}/papers",
            "{$this->basePath}/concepts",
            "{$this->basePath}/synthesis",
        ],
        'files' => [
            "{$this->basePath}/SCHEMA.md",
            "{$this->basePath}/index.md",
            "{$this->basePath}/log.md",
        ],
        'gitkeeps' => [
            "{$this->basePath}/papers/.gitkeep",
            "{$this->basePath}/concepts/.gitkeep",
            "{$this->basePath}/synthesis/.gitkeep",
        ],
    ];

    foreach ($structure['subdirs'] as $dir) {
        $this->assertTrue(File::isDirectory($dir), "Expected subdirectory {$dir}");
    }

    foreach ($structure['files'] as $file) {
        $this->assertTrue(File::exists($file), "Expected file {$file}");
    }

    foreach ($structure['gitkeeps'] as $file) {
        $this->assertTrue(File::exists($file), "Expected gitkeep {$file}");
    }
});
