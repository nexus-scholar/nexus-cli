# nexus:screen-adjudicate

Record human title/abstract or full-text adjudication decisions for a locked Nexus Scholar project.

This command is a host wrapper. It parses YAML or JSON, then delegates to `nexus-scholar/core`'s human adjudication use case. Lock checks, membership checks, decision provenance, and persistence rules live in `core`.

## Prerequisites

- Migrations have run.
- The project is locked.
- Every `work_id` in the file belongs to the locked project.
- The reviewer has a stable actor ID, such as `reviewer-1` or an application user ID.

## Example File

Print the current YAML shape:

```powershell
php artisan nexus:screen-adjudicate --example
```

Minimal YAML:

```yaml
stage: title_abstract
criteria_hash: tomato-label-efficiency-v1
run_id: human-adjudication-2026-05-22
run_name: TomatoMAP human adjudication
decisions:
  - work_id: 00000000-0000-0000-0000-000000000001
    decision: include
    reason: The title and abstract directly study tomato instance segmentation with label-efficient learning.
    evidence:
      - tomato instance segmentation
      - limited annotation budget
    confidence: 1.0
    source_decision_ids:
      - previous-screening-decision-id
```

Allowed decisions:

- `include`
- `needs_review`
- `exclude`

Allowed stages:

- `title_abstract`
- `full_text`
- `human_adjudication`

## Run

```powershell
php artisan nexus:screen-adjudicate `
  --project=tomatomap_label_efficiency `
  --actor=reviewer-1 `
  --file=storage/adjudication/tomatomap-human.yml
```

Overrides are available when the file omits or needs to override run metadata:

```powershell
php artisan nexus:screen-adjudicate `
  --project=tomatomap_label_efficiency `
  --actor=reviewer-1 `
  --file=storage/adjudication/tomatomap-human.yml `
  --stage=title_abstract `
  --criteria-hash=tomato-label-efficiency-v1 `
  --run=human-run-1 `
  --name="TomatoMAP human adjudication"
```

## Expected Output

```text
Adjudication complete.
Run: human-run-1 | Total: 12 | Include: 7 | Needs review: 2 | Exclude: 3
```

## Validation Behavior

The CLI rejects:

- missing `--project`, `--actor`, or `--file`,
- files that are not JSON/YAML,
- empty or missing `decisions`,
- rows missing `work_id`, `decision`, or `reason`,
- invalid decision or stage values,
- confidence values outside `0..1`.

The core handler rejects:

- unlocked projects,
- works outside the project,
- mismatched existing run/project/stage combinations,
- empty human rationale.
