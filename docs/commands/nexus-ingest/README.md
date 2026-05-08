# nexus:ingest

Create wiki paper pages from a run JSON file without overwriting existing pages.

## Usage

```powershell
php artisan nexus:ingest
php artisan nexus:ingest storage\runs\all_20260505_000000.json
```

## Behavior

- Reads a run JSON file (argument or `storage/runs/latest.json`).
- Creates `docs/wiki/papers/{slug}.md` for each entry if it does not exist.
- Appends a line to `docs/wiki/log.md` for each created page.
- Never overwrites existing paper pages.

## Expected Input Format

Each run entry should use the `ScholarlyWorkDto` shape from `nexus-scholar/core`:

```json
{
  "ids": [{"ns": "doi", "val": "10.1234/abc"}],
  "title": "Paper A",
  "authors": [{"family": "Doe", "given": "Jane"}],
  "year": 2024,
  "sourceProvider": "openalex"
}
```

