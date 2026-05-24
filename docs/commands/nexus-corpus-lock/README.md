# nexus:corpus-lock

Lock a Nexus Scholar project corpus and create an immutable corpus snapshot.

Use this when the project corpus membership is ready to freeze. After lock, `nexus-scholar/core` blocks corpus mutations such as search persistence and snowball additions, while still allowing screening, adjudication, comparison, graph analysis, full-text retrieval for existing works, and exports.

## Usage

```powershell
php artisan nexus:corpus-lock --project=tomatomap_label_efficiency
```

Recommended real run:

```powershell
php artisan nexus:corpus-lock `
  --project=tomatomap_label_efficiency `
  --actor=reviewer-1 `
  --reason="Final title/abstract screening corpus" `
  --metadata=scope=tomatomap `
  --metadata=stage=title_abstract
```

## Output

```text
Corpus locked.
Project: tomatomap_label_efficiency
Snapshot: 3966ebe5-b6df-4b03-a812-6d3bd560b0b9
Snapshot works: 1929
```

## Options

```text
--project=       Project ID to lock.
--actor=         Actor ID recorded in lock audit.
--reason=        Human-readable lock reason.
--metadata=*     Additional lock metadata as key=value pairs.
```

## Notes

- Locking is intentionally one-way for normal CLI use. Administrative unlock policy belongs in the host app, not this command.
- A locked project should be used for human adjudication, final screening comparison, and final/citable exports.
- Snapshot-backed exports are marked `citable=yes` and `final=yes`; draft exports before lock remain allowed but non-citable.
