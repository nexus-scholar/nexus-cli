# Nexus Scholar CLI

Laravel Artisan workspace for running Nexus Scholar systematic-review workflows on top of `nexus-scholar/core`.

This repository is currently a project-style CLI app, not a Composer library. It is intended to be cloned as a working research workspace with local storage for run JSON, screening decisions, graph outputs, PDFs, and wiki notes.

## Current Commands

| Command | Purpose |
| --- | --- |
| `php artisan nexus:wiki-init` | Create the local research wiki structure under `docs/wiki`. |
| `php artisan nexus:search` | Run YAML-defined scholarly searches through `nexus-scholar/core` and write run JSON under `storage/runs`. |
| `php artisan nexus:run-stats` | Print summary statistics for a run JSON file or `storage/runs/latest.json`. |
| `php artisan nexus:ingest` | Convert run JSON records into local paper pages. |
| `php artisan nexus:screen` | Apply deterministic and optional LLM-assisted screening criteria and write `storage/screens/{run_id}.json`. |
| `php artisan nexus:fetch-pdfs` | Fetch PDFs for included papers and write `storage/pdfs/{run_id}`. |
| `php artisan nexus:graph` | Build and analyze citation graphs from run JSON relationships. |
| `php artisan nexus:status` | Print local workspace status and latest run information. |

## Setup

The current development workspace expects sibling local packages:

```text
../core
../graph-core
../graph-algorithms
```

Requirements:

- PHP 8.4+
- Composer 2

Install and initialize:

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
```

For a fresh SQLite local setup:

```powershell
New-Item -ItemType File -Force database/database.sqlite
php artisan migrate
```

## Search Workflow

Create a YAML search plan compatible with `nexus-scholar/core`, then run either one query by id or all queries:

```powershell
php artisan nexus:search path/to/queries.yml --id=my-query
php artisan nexus:search path/to/queries.yml --all
```

Useful options:

```powershell
php artisan nexus:search path/to/queries.yml --all --project=my-project
```

Outputs:

- Per-query run files under `storage/runs`.
- A global deduplicated `all_*.json` file when running all queries.
- `storage/runs/latest.json` pointing to the latest run.

## Screening Workflow

Initialize wiki/docs if needed:

```powershell
php artisan nexus:wiki-init
```

Create criteria:

```powershell
copy storage/criteria.example.json storage/criteria.json
```

Run screening:

```powershell
php artisan nexus:screen
php artisan nexus:screen storage/runs/all_20260520_120000.json --criteria=storage/criteria.json
```

Useful options:

```powershell
php artisan nexus:screen --dry-run
php artisan nexus:screen --unknown-year=exclude
php artisan nexus:screen --allow-empty-include
php artisan nexus:screen --max-llm=25
```

## PDF Fetching

```powershell
php artisan nexus:fetch-pdfs
php artisan nexus:fetch-pdfs storage/screens/all_20260520_120000.json
```

Current limitation: this command still uses app-owned PDF lookup/download logic and should be replaced by the legal open-access full-text retrieval pipeline in `nexus-scholar/core`. Treat it as a compatibility workflow until that integration slice lands.

## Graph Workflow

```powershell
php artisan nexus:graph
php artisan nexus:graph storage/runs/all_20260520_120000.json --project=my-project
php artisan nexus:graph storage/runs/all_20260520_120000.json --source=doi:10.1000/a --target=doi:10.1000/b
```

The graph command uses `nexus-scholar/core` citation-network services where available and writes graph artifacts under local storage.

## Status And Stats

```powershell
php artisan nexus:status
php artisan nexus:run-stats
php artisan nexus:run-stats storage/runs/all_20260520_120000.json
```

## Provider Configuration

Configure provider credentials and rate limits in `.env` and Laravel config. Do not commit real API keys.

Common providers currently handled by `core` include OpenAlex, Crossref, arXiv, Semantic Scholar, PubMed, DOAJ, IEEE, Unpaywall, PMC, and Europe PMC. Some providers work without API keys; others require credentials or contact email values.

## Development Commands

```powershell
composer test
composer format
composer format:check
composer validate --strict
```

The test suite uses fake HTTP clients and fixtures where possible. CI should not depend on live provider network calls.

## Production Readiness Notes

Before treating this CLI as production-ready:

- Add commands for `core` export history, full-text retrieval, snowballing, lock/unlock, and job progress.
- Replace `nexus:fetch-pdfs` with `core` full-text retrieval.
- Add end-to-end tests for search -> screen -> full text -> graph -> export.
- Decide whether this remains a project template or becomes an installable CLI package.
