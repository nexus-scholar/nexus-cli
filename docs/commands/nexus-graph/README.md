# nexus:graph

Build and analyze citation-network artifacts from a run JSON file.

The command reads relationships already present in a run file and delegates graph construction and metrics to `nexus-scholar/core`. It writes JSON graph artifacts under `storage/graphs` unless `--dry-run` is used.

## Usage

```powershell
php artisan nexus:graph
php artisan nexus:graph storage/runs/all_20260520_120000.json --project=tomatomap_label_efficiency
php artisan nexus:graph storage/runs/all_20260520_120000.json --type=bibliographic_coupling --dry-run
```

Compute a shortest path between two known work IDs:

```powershell
php artisan nexus:graph `
  storage/runs/all_20260520_120000.json `
  --source=doi:10.1000/a `
  --target=doi:10.1000/b
```

## Output

```text
Run file: storage/runs/all_20260520_120000.json
Graph type: citation
Works: 1929
Relationships extracted: 216
Nodes: 1929
Edges: 216
Density: 0.000058
Saved: storage/graphs/all_20260520_120000_citation.json
```

## Options

```text
{run?}        Path to run JSON, defaults to storage/runs/latest.json.
--type=       Graph type: citation, co_citation, or bibliographic_coupling.
--project=    Project ID for the generated graph.
--source=     Source work ID for shortest path, for example doi:10.1000/a.
--target=     Target work ID for shortest path, for example doi:10.1000/b.
--output=     Output JSON path, defaults to storage/graphs/{runid}_{type}.json.
--dry-run     Show graph stats without writing JSON.
```

## Notes

- If `Relationships extracted` is zero, re-run search with raw provider metadata enabled or provide references in the run JSON.
- Use `--dry-run` before writing large graph outputs.
- Use `citation` for directed reference relationships, `co_citation` for shared-cited-work grouping, and `bibliographic_coupling` for shared-reference grouping.
