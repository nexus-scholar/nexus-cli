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

test('creates wiki directory and subdirectories', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $this->assertTrue(File::isDirectory($this->basePath));
    $this->assertTrue(File::isDirectory("{$this->basePath}/papers"));
    $this->assertTrue(File::isDirectory("{$this->basePath}/concepts"));
    $this->assertTrue(File::isDirectory("{$this->basePath}/synthesis"));
});

test('creates gitkeep files in subdirectories', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $this->assertTrue(File::exists("{$this->basePath}/papers/.gitkeep"));
    $this->assertTrue(File::exists("{$this->basePath}/concepts/.gitkeep"));
    $this->assertTrue(File::exists("{$this->basePath}/synthesis/.gitkeep"));
});

test('creates index md with seed content', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $indexPath = "{$this->basePath}/index.md";
    $this->assertTrue(File::exists($indexPath));

    $content = File::get($indexPath);
    $this->assertStringContainsString('# Research Wiki Index', $content);
    $this->assertStringContainsString('## Core Concepts', $content);
    $this->assertStringContainsString('## Recent Papers', $content);
    $this->assertStringContainsString('## Synthesis Reports', $content);
});

test('creates log md with seed content', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $logPath = "{$this->basePath}/log.md";
    $this->assertTrue(File::exists($logPath));

    $content = File::get($logPath);
    $this->assertStringContainsString('# Wiki Activity Log', $content);
    $this->assertStringContainsString('| Date | Action | Details |', $content);
    $this->assertStringContainsString('Init', $content);
});

test('copies schema template to schema md when template exists', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $schemaPath = "{$this->basePath}/SCHEMA.md";
    $this->assertTrue(File::exists($schemaPath));

    $templateContent = File::get($this->templatePath);
    $schemaContent = File::get($schemaPath);
    $this->assertEquals($templateContent, $schemaContent);
});

test('outputs info messages', function () {
    $this->artisan('nexus:wiki-init')
        ->expectsOutput("Created directory: {$this->basePath}")
        ->expectsOutput("Created directory: {$this->basePath}/papers")
        ->expectsOutput("Created directory: {$this->basePath}/concepts")
        ->expectsOutput("Created directory: {$this->basePath}/synthesis")
        ->expectsOutput("Created seed file: {$this->basePath}/SCHEMA.md")
        ->expectsOutput("Created seed file: {$this->basePath}/index.md")
        ->expectsOutput("Created seed file: {$this->basePath}/log.md")
        ->assertExitCode(0);
});

test('command is idempotent', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $this->assertTrue(File::isDirectory("{$this->basePath}/papers"));
    $this->assertTrue(File::isDirectory("{$this->basePath}/concepts"));
    $this->assertTrue(File::isDirectory("{$this->basePath}/synthesis"));
});

test('does not overwrite existing index md', function () {
    File::makeDirectory($this->basePath, 0755, true);
    $customContent = "# Custom Index\nCustom content here.";
    File::put("{$this->basePath}/index.md", $customContent);

    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $content = File::get("{$this->basePath}/index.md");
    $this->assertEquals($customContent, $content);
});

test('does not overwrite existing log md', function () {
    File::makeDirectory($this->basePath, 0755, true);
    $customContent = "# Custom Log\nCustom log entry.";
    File::put("{$this->basePath}/log.md", $customContent);

    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $content = File::get("{$this->basePath}/log.md");
    $this->assertEquals($customContent, $content);
});

test('does not overwrite existing schema md', function () {
    File::makeDirectory($this->basePath, 0755, true);
    $customContent = '# Custom Schema';
    File::put("{$this->basePath}/SCHEMA.md", $customContent);

    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $content = File::get("{$this->basePath}/SCHEMA.md");
    $this->assertEquals($customContent, $content);
});

test('creates empty schema when template missing', function () {
    $tempName = $this->templatePath . '.backup';
    if (File::exists($this->templatePath)) {
        File::move($this->templatePath, $tempName);
    }

    try {
        $this->artisan('nexus:wiki-init')->assertExitCode(0);

        $schemaPath = "{$this->basePath}/SCHEMA.md";
        $this->assertTrue(File::exists($schemaPath));

        $content = File::get($schemaPath);
        $this->assertStringContainsString('# Wiki Schema', $content);
    } finally {
        if (File::exists($tempName)) {
            File::move($tempName, $this->templatePath);
        }
    }
});

test("log file contains today's date", function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $logPath = "{$this->basePath}/log.md";
    $content = File::get($logPath);
    $today = date('Y-m-d');

    $this->assertStringContainsString($today, $content);
});

test('created directories are readable', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $this->assertTrue(is_readable("{$this->basePath}/papers"));
    $this->assertTrue(is_readable("{$this->basePath}/concepts"));
    $this->assertTrue(is_readable("{$this->basePath}/synthesis"));
});

test('handles partial existing structure', function () {
    File::makeDirectory($this->basePath, 0755, true);
    File::makeDirectory("{$this->basePath}/papers", 0755, true);

    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $this->assertTrue(File::isDirectory("{$this->basePath}/papers"));
    $this->assertTrue(File::isDirectory("{$this->basePath}/concepts"));
    $this->assertTrue(File::isDirectory("{$this->basePath}/synthesis"));

    $this->assertTrue(File::exists("{$this->basePath}/concepts/.gitkeep"));
    $this->assertTrue(File::exists("{$this->basePath}/synthesis/.gitkeep"));
});

test('index md starts with title', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $indexPath = "{$this->basePath}/index.md";
    $content = File::get($indexPath);
    $lines = explode("\n", $content);

    $this->assertStringStartsWith('# Research Wiki Index', $lines[0]);
});

test('handles multiple rapid executions', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->artisan('nexus:wiki-init')->assertExitCode(0);
    }

    $this->assertTrue(File::exists("{$this->basePath}/index.md"));
    $this->assertTrue(File::exists("{$this->basePath}/log.md"));
    $this->assertTrue(File::exists("{$this->basePath}/SCHEMA.md"));
});

test('wiki structure is complete', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $expectedDirs = [
        $this->basePath,
        "{$this->basePath}/papers",
        "{$this->basePath}/concepts",
        "{$this->basePath}/synthesis",
    ];

    foreach ($expectedDirs as $dir) {
        $this->assertTrue(
            File::isDirectory($dir),
            "Expected directory {$dir} to exist"
        );
    }

    $expectedFiles = [
        "{$this->basePath}/SCHEMA.md",
        "{$this->basePath}/index.md",
        "{$this->basePath}/log.md",
        "{$this->basePath}/papers/.gitkeep",
        "{$this->basePath}/concepts/.gitkeep",
        "{$this->basePath}/synthesis/.gitkeep",
    ];

    foreach ($expectedFiles as $file) {
        $this->assertTrue(
            File::exists($file),
            "Expected file {$file} to exist"
        );
    }
});

test('command returns success code', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);
});

test('gitkeep files are empty', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $this->assertEquals('', File::get("{$this->basePath}/papers/.gitkeep"));
    $this->assertEquals('', File::get("{$this->basePath}/concepts/.gitkeep"));
    $this->assertEquals('', File::get("{$this->basePath}/synthesis/.gitkeep"));
});

test('schema md is not empty when template exists', function () {
    $this->artisan('nexus:wiki-init')->assertExitCode(0);

    $schemaPath = "{$this->basePath}/SCHEMA.md";
    $content = File::get($schemaPath);

    $this->assertNotEmpty($content);
    $this->assertGreaterThan(100, strlen($content));
});
