# 1.0 Release Smoke

Last updated: 2026-05-27

This smoke is the CLI host side of the `nexus-scholar/core` 1.0 release gate. It verifies that production-facing CLI adapters remain thin over the core package and can run against fixture-backed data without live provider calls.

## Fixture-Backed Coverage

Current focused smoke:

```powershell
php artisan test --filter=NexusReadCommandsTest
```

Result on 2026-05-27: passed, 2 tests and 18 assertions.

This covers:

- `nexus:exports` over `ExportHistoryReaderPort`,
- `nexus:jobs` over `JobLifecycleReaderPort`,
- `nexus:full-text-artifacts` over `FullTextFetchReaderPort`,
- ambiguous selector failures for read commands.

Related existing command coverage:

- `tests/Feature/Commands/NexusCorpusLockExportTest.php`
- `tests/Feature/Commands/NexusScreenAdjudicateCompareTest.php`
- `tests/Feature/Commands/NexusFetchPdfsTest.php`
- `tests/Feature/Commands/NexusGraphTest.php`

## Final 1.0 Host Gate

Run before declaring the CLI host ready against a `core` 1.0 release candidate:

```powershell
composer validate --strict
composer audit --format=plain
composer test
composer format:check
git diff --check
php artisan list nexus
```

The command list must include host read commands for exports, jobs, and full-text artifacts, in addition to the existing search, screening, lock, full-text retrieval, graph, and export workflows.

## Current Branch Validation

2026-05-27 validation on `cdx/cli-1-0-readiness`:

- `composer validate --strict`: passed.
- `composer audit --format=plain`: passed, no advisories.
- `composer test`: passed, 98 tests and 563 assertions.
- `composer format:check`: passed.
- `git diff --check`: passed.
- `php artisan list nexus`: passed and listed `nexus:exports`, `nexus:jobs`, and `nexus:full-text-artifacts`.

## Core Consumer Smoke Dependency

The matching `core` branch passed a local Laravel consumer smoke on 2026-05-27 by advertising the local path checkout as Composer version `1.0.0` and requiring `nexus-scholar/core:^1.0`.

Relevant result:

- `composer require nexus-scholar/core:^1.0`: passed.
- `php artisan vendor:publish --tag=nexus-config --force`: passed.
- `php artisan vendor:publish --tag=nexus-migrations --force`: passed.
- `php artisan migrate:fresh --force`: passed through the corpus snapshot migration.
- `php artisan list nexus`: passed and listed the package-owned `nexus:search` and `nexus:screen` commands.
- `composer audit --format=plain`: passed with no advisories.
