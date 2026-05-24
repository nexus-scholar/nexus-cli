# nexus:export-bibliography

Export project bibliography through `nexus-scholar/core` and record export history metadata.

The command reads project corpus membership through core. For locked projects, membership comes from the immutable corpus snapshot. This is the preferred path for final/citable bibliography exports.

## Usage

```powershell
php artisan nexus:export-bibliography --project=tomatomap_label_efficiency
```

Choose a format and path:

```powershell
php artisan nexus:export-bibliography `
  --project=tomatomap_label_efficiency `
  --format=bibtex `
  --output=exports/tomatomap-final.bib `
  --requested-by=reviewer-1
```

Limit to specific query IDs when needed:

```powershell
php artisan nexus:export-bibliography `
  --project=tomatomap_label_efficiency `
  --query-ids=query-a,query-b `
  --format=csv
```

## Output

```text
Bibliography exported.
Project: tomatomap_label_efficiency
Works: 1929
Path: exports/tomatomap-final.bib
Project locked: yes
Snapshot: 3966ebe5-b6df-4b03-a812-6d3bd560b0b9
Citable: yes
Final: yes
```

## Options

```text
--project=        Project ID to export.
--format=csv      Bibliography format: bibtex, ris, csv, json, or jsonl.
--output=         Storage path. Defaults to exports/{project}-{timestamp}.{ext}.
--query-ids=      Optional comma-separated search query IDs.
--requested-by=   Actor ID for export history.
```

## Interpretation

- `Project locked: yes` means core found an active project lock.
- `Snapshot` is populated when the export is backed by an immutable corpus snapshot.
- `Citable: yes` and `Final: yes` are expected for locked snapshot-backed exports.
- Draft exports before lock are valid for review but should not be cited as final.
