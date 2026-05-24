# Screening Pipeline

End-to-end workflow:

1. `nexus:search --all`
2. `nexus:ingest`
3. Create `storage/criteria.json`
4. `nexus:screen`
5. `nexus:screen-compare --list-runs` when comparing persisted project screening runs
6. `nexus:fetch-full-text`

## Notes

- `nexus:screen` writes `storage/screens/{run_id}.json`.
- `nexus:screen-compare --list-runs` helps discover persisted run IDs before comparing deterministic, LLM, council, or human adjudication runs.
- `nexus:fetch-full-text` stores legal OA full-text artifacts and `manifest.json` under `full-text/{run_id}` on the configured Laravel storage disk by default.
- `nexus:fetch-pdfs` remains available as a backward-compatible alias for `nexus:fetch-full-text`.
- When criteria are ready, add them to `storage/criteria.json`.
