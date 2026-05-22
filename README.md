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
| `php artisan nexus:fetch-full-text` | Retrieve legal open-access full text for included papers through `nexus-scholar/core`. |
| `php artisan nexus:fetch-pdfs` | Backward-compatible alias for full-text retrieval. |
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

There are two screening paths:

- Run-file mode reads local run JSON and writes `storage/screens/{run_id}.json`.
- Project mode uses `nexus-scholar/core` and persists `screening_runs`, `screening_decisions`, and `screening_votes`.

Use project mode for real title/abstract review work:

```powershell
php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --include="crop image segmentation" `
  --include="label-efficient or semi-supervised visual recognition" `
  --exclude="medical imaging" `
  --exclude="remote sensing only" `
  --model=openai/gpt-4.1-mini `
  --max=10 `
  --name="TomatoMAP title abstract smoke"
```

Enable OpenRouter-backed LLM screening in `.env`:

```dotenv
NEXUS_LLM_SCREENING_ENABLED=true
NEXUS_LLM_OPENROUTER_API_KEY=
NEXUS_LLM_SCREENING_MODEL=openai/gpt-4.1-mini
```

Then clear cached config:

```powershell
php artisan config:clear
```

Council mode runs multiple model attempts and aggregates the result:

```powershell
php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --mode=council `
  --council-models="openai/gpt-4.1-mini,google/gemini-2.5-flash,mistralai/mistral-small-2603" `
  --include="crop image segmentation" `
  --exclude="medical imaging" `
  --max=3
```

See [docs/commands/nexus-screen/README.md](docs/commands/nexus-screen/README.md) for criteria files, audit flags, DB inspection commands, and the legacy run-file mode.

## Full Text Retrieval

```powershell
php artisan nexus:fetch-full-text
php artisan nexus:fetch-full-text storage/screens/all_20260520_120000.json
php artisan nexus:fetch-full-text storage/screens/all_20260520_120000.json --destination=full-text/my-run
```

The legacy `nexus:fetch-pdfs` command remains available for older workflows, but both command names now delegate to the `nexus-scholar/core` full-text retrieval pipeline. They can store validated PDFs and supported XML/text artifacts from configured legal open-access sources such as direct run metadata URLs, Unpaywall, PMC, Europe PMC, arXiv, OpenAlex metadata PDF URLs, and Semantic Scholar metadata PDF URLs.

Artifacts and `manifest.json` are written to the configured Laravel storage disk under `full-text/{run_id}` by default. Configure the disk and source settings in `.env`:

```powershell
NEXUS_FULL_TEXT_DISK=public
NEXUS_UNPAYWALL_EMAIL=you@example.com
```

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

- Add commands for `core` export history, snowballing, lock/unlock, and job progress.
- Add end-to-end tests for search -> screen -> full text -> graph -> export.
- Decide whether this remains a project template or becomes an installable CLI package.
