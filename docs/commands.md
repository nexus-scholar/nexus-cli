# Artisan Command Index

This file is the high-level command map for the Nexus Scholar CLI host app. Detailed examples live under `docs/commands/*/README.md`.

Run the live command list with:

```powershell
php artisan list nexus
```

## Workspace Commands

### `nexus:status`

Status: exists.

Shows local workspace status, latest run information, baseline summary when present, and wiki health counts.

Typical use:

```powershell
php artisan nexus:status
```

### `nexus:wiki-init`

Status: exists.

Initializes the local research wiki folder structure under `docs/wiki`.

Typical use:

```powershell
php artisan nexus:wiki-init
```

### `nexus:run-stats`

Status: exists.

Prints quick statistics for a run JSON file, or for the latest run pointer when no file is provided.

Typical use:

```powershell
php artisan nexus:run-stats
php artisan nexus:run-stats storage/runs/all_20260520_120000.json
```

## Search And Ingest

### `nexus:search`

Status: exists.

Runs YAML-defined scholarly search plans through `nexus-scholar/core`, persists project-mode search data when `--project` is provided, and writes run JSON files under `storage/runs`.

Typical use:

```powershell
php artisan nexus:search queries/tomatomap-label-efficient-instance-segmentation.yml --all --project=tomatomap_label_efficiency
php artisan nexus:search queries/tomatomap-label-efficient-instance-segmentation.yml --id=tomatomap-core --project=tomatomap_label_efficiency
```

Outputs:

- Per-query run files under `storage/runs`.
- A global deduplicated `all_*.json` file when running all queries.
- `storage/runs/latest.json` pointing to the latest run.
- Project, query, work, provider, and provenance rows in the database when project mode is used.

### `nexus:ingest`

Status: exists.

Creates local paper wiki pages from a run JSON file without overwriting existing pages.

Typical use:

```powershell
php artisan nexus:ingest
php artisan nexus:ingest storage/runs/all_20260520_120000.json
```

See `docs/commands/nexus-ingest/README.md`.

## Corpus Lock And Export

### `nexus:corpus-lock`

Status: exists.

Locks a project corpus through `nexus-scholar/core` and creates an immutable corpus snapshot. Locked projects block corpus mutation while allowing review, graph, full-text, and final export workflows over the frozen membership.

Typical use:

```powershell
php artisan nexus:corpus-lock `
  --project=tomatomap_label_efficiency `
  --actor=reviewer-1 `
  --reason="Final title/abstract screening corpus" `
  --metadata=scope=tomatomap `
  --metadata=stage=title_abstract
```

See `docs/commands/nexus-corpus-lock/README.md`.

### `nexus:export-bibliography`

Status: exists.

Exports project bibliography through `nexus-scholar/core` and records export history with lock, snapshot, citable, and final metadata.

Typical use:

```powershell
php artisan nexus:export-bibliography --project=tomatomap_label_efficiency --format=csv
php artisan nexus:export-bibliography --project=tomatomap_label_efficiency --format=bibtex --output=exports/tomatomap-final.bib
```

See `docs/commands/nexus-export-bibliography/README.md`.

### `nexus:exports`

Status: exists.

Reads export history through `nexus-scholar/core` without direct SQL. Use it to list recent exports by project or inspect one export record by ID.

Typical use:

```powershell
php artisan nexus:exports --project=tomatomap_label_efficiency --limit=10
php artisan nexus:exports export-history-id --json
```

See `docs/commands/nexus-read-apis/README.md`.

## Job Lifecycle

### `nexus:jobs`

Status: exists.

Reads job lifecycle progress through `nexus-scholar/core`. Use `--run` for one run timeline, `--project` for recent project activity, and `--status` for the latest run status.

Typical use:

```powershell
php artisan nexus:jobs --run=run-20260527-001
php artisan nexus:jobs --run=run-20260527-001 --status
php artisan nexus:jobs --project=tomatomap_label_efficiency --limit=25 --json
```

See `docs/commands/nexus-read-apis/README.md`.

## Screening

### `nexus:screen`

Status: exists.

Supports two screening paths:

- Run-file mode screens local run JSON and writes `storage/screens/{run_id}.json`.
- Project mode delegates to `nexus-scholar/core` and persists `screening_runs`, `screening_decisions`, and `screening_votes`.

Typical project-mode use:

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

See `docs/commands/nexus-screen/README.md`.

### `nexus:screen-adjudicate`

Status: exists.

Records human adjudication decisions for a locked project through `nexus-scholar/core`.

Typical use:

```powershell
php artisan nexus:screen-adjudicate --example

php artisan nexus:screen-adjudicate `
  --project=tomatomap_label_efficiency `
  --actor=reviewer-1 `
  --file=storage/adjudication/tomatomap-human.yml
```

See `docs/commands/nexus-screen-adjudicate/README.md`.

### `nexus:screen-compare`

Status: exists.

Lists recent persisted runs for a project, or compares two persisted screening runs through `nexus-scholar/core` and prints agreement, disagreement, transition counts, missing rows, and optional JSON.

Typical use:

```powershell
php artisan nexus:screen-compare --project=tomatomap_label_efficiency --list-runs

php artisan nexus:screen-compare `
  --project=tomatomap_label_efficiency `
  --baseline-run=rules-run-id `
  --candidate-run=human-run-id `
  --stage=title_abstract
```

See `docs/commands/nexus-screen-compare/README.md`.

## Full Text

### `nexus:fetch-full-text`

Status: exists.

Retrieves legal open-access full text for included papers through `nexus-scholar/core`, writes artifacts to the configured Laravel storage disk under `full-text/{run_id}` by default, and writes `manifest.json`.

Typical use:

```powershell
php artisan nexus:fetch-full-text
php artisan nexus:fetch-full-text storage/screens/all_20260520_120000.json
php artisan nexus:fetch-full-text storage/screens/all_20260520_120000.json --json
```

See `docs/commands/nexus-fetch-full-text/README.md`.

### `nexus:fetch-pdfs`

Status: exists as a backward-compatible alias.

This command has the same options and behavior as `nexus:fetch-full-text`. Prefer `nexus:fetch-full-text` in new docs and scripts.

See `docs/commands/nexus-fetch-pdfs/README.md`.

### `nexus:full-text-artifacts`

Status: exists.

Reads full-text fetch audit records through `nexus-scholar/core` by work ID or project corpus.

Typical use:

```powershell
php artisan nexus:full-text-artifacts --project=tomatomap_label_efficiency --limit=25
php artisan nexus:full-text-artifacts --work=doi:10.5555/example --json
```

See `docs/commands/nexus-read-apis/README.md`.

## Graph

### `nexus:graph`

Status: exists.

Builds and analyzes citation, co-citation, or bibliographic-coupling graphs from run JSON relationships using `nexus-scholar/core` graph services.

Typical use:

```powershell
php artisan nexus:graph
php artisan nexus:graph storage/runs/all_20260520_120000.json --project=tomatomap_label_efficiency
php artisan nexus:graph storage/runs/all_20260520_120000.json --type=bibliographic_coupling --dry-run
```

See `docs/commands/nexus-graph/README.md`.

## Current Workflow

For a real DB-backed systematic-review workflow:

1. Run `nexus:search --all --project=...`.
2. Review result stats with `nexus:run-stats`.
3. Run project-mode `nexus:screen`.
4. Lock the corpus with `nexus:corpus-lock` when corpus membership is ready to freeze.
5. Record human decisions with `nexus:screen-adjudicate`.
6. Compare runs with `nexus:screen-compare`.
7. Retrieve legal OA artifacts with `nexus:fetch-full-text`.
8. Inspect full-text fetch history with `nexus:full-text-artifacts`.
9. Build graph artifacts with `nexus:graph`.
10. Export final/citable bibliography with `nexus:export-bibliography`.
11. Inspect export history with `nexus:exports` and job progress with `nexus:jobs`.

Run-file workflows remain available for lightweight local exploration, but project mode is the preferred path for citable review work.
