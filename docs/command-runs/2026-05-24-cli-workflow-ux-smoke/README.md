# CLI Workflow UX Smoke - 2026-05-24

Purpose: verify the command UX added for screening comparison run discovery and full-text retrieval summaries.

## Environment

- Repo: `nexus-scholar/nexus-cli`
- Branch: `cdx/cli-workflow-ux-docs`
- Date: 2026-05-24

## Screening Run Discovery

Command:

```powershell
php artisan nexus:screen-compare --project=tomatomap_label_efficiency --list-runs --limit=5 --json
```

Finding:

- Exit code: 0
- The command returned the 5 newest persisted screening runs for `tomatomap_label_efficiency`.
- The newest two usable comparison IDs were:
  - `locked-snapshot-human-20260522` (`human`, `title_abstract`, completed)
  - `a111f8e5310cbe5138e8d90e9fd3238f` (`llm_single`, `title_abstract`, completed)
- Counts are now visible in the CLI output through `counts_summary`, for example `total:1 included:0 needs_review:1 excluded:0 failed:0`.

## Screening Comparison

Command:

```powershell
php artisan nexus:screen-compare `
  --project=tomatomap_label_efficiency `
  --baseline-run=a111f8e5310cbe5138e8d90e9fd3238f `
  --candidate-run=locked-snapshot-human-20260522 `
  --stage=title_abstract `
  --no-rows
```

Output:

```text
Screening comparison complete.
Project: tomatomap_label_efficiency | Baseline: a111f8e5310cbe5138e8d90e9fd3238f (llm_single/title_abstract) | Candidate: locked-snapshot-human-20260522 (human/title_abstract)
Comparable: 1 | Agreement: 1 (100.0%) | Disagreement: 0 (0.0%)
Missing in baseline: 0 | Missing in candidate: 0
needs_review -> needs_review: 1
Reference run: locked-snapshot-human-20260522
```

Finding: the new metadata line makes it clear which run ID, mode, and stage are being compared before reading agreement metrics.

## Full-Text Retrieval JSON Summary

The checkout did not have a reusable real `storage/screens` artifact after the test suite reset temporary storage, so this smoke used a local no-network fixture with one included work and no legal OA source URL.

Command:

```powershell
php artisan nexus:fetch-full-text storage/screens/cli_smoke_20260524_000000.json --json
```

Output:

```json
{
    "screen_file": "storage/screens/cli_smoke_20260524_000000.json",
    "run_id": "cli_smoke_20260524_000000",
    "destination": "full-text/cli_smoke_20260524_000000",
    "manifest_path": "full-text/cli_smoke_20260524_000000/manifest.json",
    "total": 1,
    "success": 0,
    "failed": 0,
    "skipped": 1
}
```

Finding: the command now reports screen file, run ID, destination, manifest path, and outcome counts in a script-friendly format without requiring network access.
