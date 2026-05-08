# Screening Pipeline

End-to-end workflow:

1. `nexus:search --all`
2. `nexus:ingest`
3. Create `storage/criteria.json`
4. `nexus:screen`
5. `nexus:fetch-pdfs`

## Notes

- `nexus:screen` writes `storage/screens/{run_id}.json`.
- `nexus:fetch-pdfs` downloads PDFs to `storage/pdfs/{run_id}`.
- When criteria are ready, add them to `storage/criteria.json`.

