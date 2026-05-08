# Screening Pipeline (Draft)

This repo now supports the screening pipeline after `nexus:search` and `nexus:ingest`:

1. `nexus:search --all`
2. `nexus:ingest`
3. Create `storage/criteria.json`
4. `nexus:screen`
5. `nexus:fetch-pdfs`

## Notes

- `storage/criteria.example.json` is a template you can copy to `storage/criteria.json`.
- `nexus:screen` writes decisions to `storage/screens/{run_id}.json`.
- `nexus:fetch-pdfs` downloads PDFs via OpenAlex and writes a manifest in `storage/pdfs/{run_id}`.

