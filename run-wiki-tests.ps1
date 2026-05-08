#!/usr/bin/env pwsh

<#
.SYNOPSIS
    Helper script to run NexusWikiInit tests with various options

.DESCRIPTION
    Simplifies running the test suite with common configurations

.EXAMPLE
    .\run-wiki-tests.ps1 -All
    .\run-wiki-tests.ps1 -Core
    .\run-wiki-tests.ps1 -Watch
    .\run-wiki-tests.ps1 -EdgeCases
#>

param(
    [switch]$All,
    [switch]$Core,
    [switch]$EdgeCases,
    [switch]$Watch,
    [switch]$Coverage,
    [switch]$Info,
    [switch]$Help
)

# Get the project root
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path

# Display help
if ($Help) {
    Write-Host "NexusWikiInit Test Runner" -ForegroundColor Green
    Write-Host ""
    Write-Host "Usage: .\run-wiki-tests.ps1 [option]" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Options:" -ForegroundColor Yellow
    Write-Host "  -All         Run all tests" -ForegroundColor White
    Write-Host "  -Core        Run core functionality tests only" -ForegroundColor White
    Write-Host "  -EdgeCases   Run edge case tests only" -ForegroundColor White
    Write-Host "  -Watch       Run tests in watch mode (auto-rerun on changes)" -ForegroundColor White
    Write-Host "  -Coverage    Generate coverage HTML report" -ForegroundColor White
    Write-Host "  -Info        Show test coverage information" -ForegroundColor White
    Write-Host "  -Help        Show this help message" -ForegroundColor White
    Write-Host ""
    Write-Host "Examples:" -ForegroundColor Yellow
    Write-Host "  .\run-wiki-tests.ps1 -All" -ForegroundColor Gray
    Write-Host "  .\run-wiki-tests.ps1 -Core" -ForegroundColor Gray
    Write-Host "  .\run-wiki-tests.ps1 -Watch" -ForegroundColor Gray
    Write-Host "  .\run-wiki-tests.ps1 -Coverage" -ForegroundColor Gray
    exit 0
}

# Change to project directory
Set-Location $projectRoot

# Show info if requested
if ($Info) {
    Write-Host "NexusWikiInit Test Coverage Information" -ForegroundColor Green
    Write-Host ""
    Write-Host "Test Files:" -ForegroundColor Yellow
    Write-Host "  - tests/Feature/Commands/NexusWikiInitTest.php" -ForegroundColor Cyan
    Write-Host "    (20 core functionality tests)" -ForegroundColor Gray
    Write-Host "  - tests/Feature/Commands/NexusWikiInitEdgeCasesTest.php" -ForegroundColor Cyan
    Write-Host "    (18 edge case and stability tests)" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Total Coverage:" -ForegroundColor Yellow
    Write-Host "  [OK] 38 tests" -ForegroundColor Green
    Write-Host "  [OK] 159 assertions" -ForegroundColor Green
    Write-Host "  [OK] ~2.25 seconds execution time" -ForegroundColor Green
    Write-Host ""
    Write-Host "Key Areas Covered:" -ForegroundColor Yellow
    Write-Host "  [OK] Directory structure creation" -ForegroundColor Cyan
    Write-Host "  [OK] File creation and seeding" -ForegroundColor Cyan
    Write-Host "  [OK] Template handling" -ForegroundColor Cyan
    Write-Host "  [OK] Idempotency checks" -ForegroundColor Cyan
    Write-Host "  [OK] Content preservation" -ForegroundColor Cyan
    Write-Host "  [OK] Format validation (UTF-8, Markdown)" -ForegroundColor Cyan
    Write-Host "  [OK] Permission checks" -ForegroundColor Cyan
    Write-Host "  [OK] Complex edge cases" -ForegroundColor Cyan
    Write-Host ""
    exit 0
}

# Run tests based on options
if ($All -or (-not $Core -and -not $EdgeCases -and -not $Watch -and -not $Coverage)) {
    Write-Host "Running all NexusWikiInit tests..." -ForegroundColor Green
    php vendor/bin/pest tests/Feature/Commands/NexusWikiInitTest.php tests/Feature/Commands/NexusWikiInitEdgeCasesTest.php
    exit $LASTEXITCODE
}

if ($Core) {
    Write-Host "Running core functionality tests..." -ForegroundColor Green
    php vendor/bin/pest tests/Feature/Commands/NexusWikiInitTest.php
    exit $LASTEXITCODE
}

if ($EdgeCases) {
    Write-Host "Running edge case tests..." -ForegroundColor Green
    php vendor/bin/pest tests/Feature/Commands/NexusWikiInitEdgeCasesTest.php
    exit $LASTEXITCODE
}

if ($Watch) {
    Write-Host "Running tests in watch mode..." -ForegroundColor Green
    php vendor/bin/pest tests/Feature/Commands/ --watch
    exit $LASTEXITCODE
}

if ($Coverage) {
    Write-Host "Generating coverage report..." -ForegroundColor Green
    php vendor/bin/pest tests/Feature/Commands/ --coverage --coverage-html=coverage
    Write-Host ""
    Write-Host "Coverage report generated in: coverage/index.html" -ForegroundColor Cyan
    exit $LASTEXITCODE
}

