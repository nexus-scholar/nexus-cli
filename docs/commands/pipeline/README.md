# Screening Pipeline

End-to-end workflow:

1. `nexus:search --all --project=...`
2. `nexus:ingest`
3. Create `storage/criteria.json`
4. `nexus:screen`
5. `nexus:corpus-lock` when corpus membership is ready to freeze
6. `nexus:screen-adjudicate` for human decisions
7. `nexus:screen-compare --list-runs` when comparing persisted project screening runs
8. `nexus:fetch-full-text`
9. `nexus:graph`
10. `nexus:export-bibliography`

## Notes

- `nexus:screen` writes `storage/screens/{run_id}.json`.
- `nexus:corpus-lock` creates the immutable snapshot that backs final/citable exports.
- `nexus:screen-adjudicate` requires a locked project and records human decisions without overwriting model or rules runs.
- `nexus:screen-compare --list-runs` helps discover persisted run IDs before comparing deterministic, LLM, council, or human adjudication runs.
- `nexus:fetch-full-text` stores legal OA full-text artifacts and `manifest.json` under `full-text/{run_id}` on the configured Laravel storage disk by default.
- `nexus:fetch-pdfs` remains available as a backward-compatible alias for `nexus:fetch-full-text`.
- `nexus:export-bibliography` reports whether the export is locked, snapshot-backed, final, and citable.
- When criteria are ready, add them to `storage/criteria.json`.
