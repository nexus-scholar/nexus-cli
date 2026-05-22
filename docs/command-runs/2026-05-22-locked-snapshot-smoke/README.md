# 2026-05-22 Locked Corpus Snapshot Smoke

Purpose: verify the production-hardening slice against the real local SQLite app database after adding immutable corpus snapshots in `nexus-scholar/core`.

## Source Data

Repository: `nexus-scholar/nexus-cli`

Database: `database/database.sqlite`

Project: `tomatomap_label_efficiency`

Initial database inspection:

```text
project.status = draft
project.locked_at = null
search_queries = 49
inferred query-work membership = 1929 works
corpus_snapshots = 0
```

The database already contained real persisted search and screening data. No synthetic fixture was needed. `storage/runs` did not contain usable run JSON files, so this smoke used the DB-backed project commands and existing work IDs.

Smoke work:

```text
c17bc19e-0fa0-453f-bb1e-5879b7723148
39. Semi-supervised semantic segmentation for grape bunch identification in natural images
```

## Migration

```powershell
php artisan migrate --force
```

Result:

```text
2026_04_28_000010_create_corpus_snapshots_table ... DONE
```

## Lock And Snapshot

```powershell
php artisan nexus:corpus-lock `
  --project=tomatomap_label_efficiency `
  --actor=codex-smoke `
  --reason="immutable snapshot smoke" `
  --metadata=source=local-smoke `
  --metadata=date=2026-05-22
```

Result:

```text
Corpus locked.
Project: tomatomap_label_efficiency
Snapshot: 3966ebe5-b6df-4b03-a812-6d3bd560b0b9
Snapshot works: 1929
```

Database verification:

```text
project.status = locked
project.locked_by = codex-smoke
snapshot.work_count = 1929
corpus_snapshot_works rows for snapshot = 1929
```

## Locked Screening

LLM calls were disabled for this smoke so the command exercised the locked membership and persistence path without spending provider calls.

```powershell
$env:NEXUS_LLM_SCREENING_ENABLED = 'false'
php artisan config:clear

php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --work-ids=c17bc19e-0fa0-453f-bb1e-5879b7723148 `
  --include="crop image segmentation" `
  --include="label-efficient or semi-supervised visual recognition" `
  --exclude="medical imaging" `
  --exclude="remote sensing only" `
  --model=openai/gpt-4.1-mini `
  --max=1 `
  --name="Locked snapshot smoke 20260522"
```

Result:

```text
Screening complete.
Run: a111f8e5310cbe5138e8d90e9fd3238f | Total: 1 | Include: 0 | Needs review: 1 | Exclude: 0 | Failed: 0
```

## Human Adjudication

Input file:

```text
docs/command-runs/2026-05-22-locked-snapshot-smoke/adjudication-smoke.yml
```

Command:

```powershell
php artisan nexus:screen-adjudicate `
  --project=tomatomap_label_efficiency `
  --actor=codex-reviewer `
  --file=docs/command-runs/2026-05-22-locked-snapshot-smoke/adjudication-smoke.yml
```

Result:

```text
Adjudication complete.
Run: locked-snapshot-human-20260522 | Total: 1 | Include: 0 | Needs review: 1 | Exclude: 0
```

## Screening Comparison

```powershell
php artisan nexus:screen-compare `
  --project=tomatomap_label_efficiency `
  --baseline-run=a111f8e5310cbe5138e8d90e9fd3238f `
  --candidate-run=locked-snapshot-human-20260522 `
  --stage=title_abstract `
  --no-rows
```

Result:

```text
Screening comparison complete.
Comparable: 1 | Agreement: 1 (100.0%) | Disagreement: 0 (0.0%)
Missing in baseline: 0 | Missing in candidate: 0
needs_review -> needs_review: 1
Reference run: locked-snapshot-human-20260522
```

## Citable Export

```powershell
php artisan nexus:export-bibliography `
  --project=tomatomap_label_efficiency `
  --format=csv `
  --output=exports/tomatomap-locked-smoke-20260522.csv `
  --requested-by=codex-smoke
```

Result:

```text
Bibliography exported.
Project: tomatomap_label_efficiency
Works: 1929
Path: exports/tomatomap-locked-smoke-20260522.csv
Project locked: yes
Snapshot: 3966ebe5-b6df-4b03-a812-6d3bd560b0b9
Citable: yes
Final: yes
```

Export history metadata:

```json
{
  "project_locked": true,
  "locked_at": "2026-05-22T13:47:28+00:00",
  "lock_status": "locked",
  "corpus_snapshot_id": "3966ebe5-b6df-4b03-a812-6d3bd560b0b9",
  "snapshot_work_count": 1929,
  "citable": true,
  "final": true
}
```

## Mutation Block

The search query count was checked before and after trying to run a new search against the locked project.

```powershell
php artisan nexus:search `
  --file=queries/tomatomap-label-efficient-instance-segmentation.yml `
  --id=TMAP_CORE01 `
  --project=tomatomap_label_efficiency
```

Result:

```text
Running 1 query from tomatomap-label-efficient-instance-segmentation.yml (project: tomatomap_label_efficiency)
Cannot perform search on locked project tomatomap_label_efficiency
exit_code=1
```

Database verification:

```text
search_queries before = 49
search_queries after = 49
```

This confirms the persistent search runner now checks the lock before creating search query/provenance rows.

## Findings

- Locking the real project created an immutable snapshot with exactly the current 1929 inferred query-work members.
- Screening and human adjudication succeeded after lock using existing snapshot members.
- Screening comparison worked against persisted database runs.
- The bibliography export was marked citable/final only because it referenced snapshot `3966ebe5-b6df-4b03-a812-6d3bd560b0b9`.
- Search persistence was blocked after lock and did not add a new `search_queries` row.
- Flat-file `storage/runs` artifacts were not available in this checkout, so graph/full-text flat-file commands were not part of this smoke. The core locked-membership checks for graph and full-text are covered by package tests.
