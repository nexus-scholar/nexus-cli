# nexus:fetch-full-text

Retrieve legal open-access full text for included papers from a screening file.

`nexus:fetch-full-text` is the preferred command. `nexus:fetch-pdfs` remains as a backward-compatible alias, but both commands call the same `nexus-scholar/core` full-text retrieval pipeline.

## Usage

```powershell
php artisan nexus:fetch-full-text
php artisan nexus:fetch-full-text storage/screens/all_20260520_120000.json
php artisan nexus:fetch-full-text storage/screens/all_20260520_120000.json --destination=full-text/tomatomap-smoke
```

Use JSON output for notebooks, CI smoke checks, or scripts:

```powershell
php artisan nexus:fetch-full-text storage/screens/all_20260520_120000.json --json
```

## Output

Text output includes the source screen file, destination folder, retrieval counts, and manifest path:

```text
Screen: storage/screens/all_20260520_120000.json
Destination: full-text/all_20260520_120000
Retrieved full text: 3 total, 2 success, 1 failed, 0 skipped.
Manifest: full-text/all_20260520_120000/manifest.json
```

JSON output has the same summary fields:

```json
{
    "screen_file": "storage/screens/all_20260520_120000.json",
    "run_id": "all_20260520_120000",
    "destination": "full-text/all_20260520_120000",
    "manifest_path": "full-text/all_20260520_120000/manifest.json",
    "total": 3,
    "success": 2,
    "failed": 1,
    "skipped": 0
}
```

## Behavior

- Reads included decisions from `storage/screens/{run_id}.json` or an explicit screen file.
- Resolves legal OA sources through `nexus-scholar/core`, including direct run metadata URLs, Unpaywall, PMC, Europe PMC, arXiv, OpenAlex metadata PDF URLs, and Semantic Scholar metadata PDF URLs when configured.
- Saves validated PDFs and supported XML/text artifacts to the configured Laravel storage disk under `full-text/{run_id}` by default.
- Writes a `manifest.json` with one row per included paper attempted.
- Keeps the same PDF validation, retry, cooldown, audit, and deterministic storage-path behavior from core.

## Options

```text
{screen?}                Path to screen JSON, defaults to storage/runs/latest.json -> storage/screens/{run_id}.json
--destination=           Storage-disk folder, defaults to full-text/{run_id}
--max-attempts=2         Max download attempts per source
--max-bytes=50000000     Max artifact size in bytes
--cooldown=3600          Seconds before retrying a recently failed source URL
--json                   Output a machine-readable retrieval summary
```
