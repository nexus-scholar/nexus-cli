# nexus:fetch-pdfs

Fetch PDFs for included papers from a screen file and save them to `storage/pdfs/{run_id}/`.

## Usage

```powershell
php artisan nexus:fetch-pdfs
php artisan nexus:fetch-pdfs storage\screens\all_20260505_000000.json
```

## Behavior

- Reads included titles from the screen file.
- Resolves PDF URLs via OpenAlex (DOI required).
- Downloads PDFs to `storage/pdfs/{run_id}/` and writes `manifest.json`.

