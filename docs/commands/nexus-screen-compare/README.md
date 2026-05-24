# nexus:screen-compare

Compare two persisted Nexus Scholar screening runs.

This command is a host wrapper over `nexus-scholar/core`'s screening run comparison use case. It does not implement screening logic. It formats agreement, disagreement, missing rows, transition counts, and optional per-work rows.

## Use Cases

- Compare deterministic screening against an LLM run.
- Compare single-model screening against council screening.
- Compare council screening against human adjudication.
- Quantify where a new screening strategy changed decisions.

## Run

List recent runs first when you need the persisted run IDs:

```powershell
php artisan nexus:screen-compare `
  --project=tomatomap_label_efficiency `
  --list-runs
```

JSON run discovery is useful when copying IDs into a notebook or report:

```powershell
php artisan nexus:screen-compare `
  --project=tomatomap_label_efficiency `
  --list-runs `
  --json
```

Then compare two runs:

```powershell
php artisan nexus:screen-compare `
  --project=tomatomap_label_efficiency `
  --baseline-run=rules-run-id `
  --candidate-run=human-run-id `
  --stage=title_abstract
```

Output:

```text
Screening comparison complete.
Project: tomatomap_label_efficiency | Baseline: rules-run-id (rules/title_abstract) | Candidate: human-run-id (human/title_abstract)
Comparable: 24 | Agreement: 21 (87.5%) | Disagreement: 3 (12.5%)
Missing in baseline: 0 | Missing in candidate: 1
exclude -> include: 2
needs_review -> include: 1
Reference run: human-run-id
```

## JSON Output

Use JSON for notebooks, reports, or downstream comparison scripts:

```powershell
php artisan nexus:screen-compare `
  --project=tomatomap_label_efficiency `
  --baseline-run=rules-run-id `
  --candidate-run=human-run-id `
  --stage=title_abstract `
  --json
```

The JSON result includes baseline and candidate run metadata, comparable totals, agreement/disagreement rates, transition counts, missing work IDs, reference run ID, and per-work rows unless `--no-rows` is passed.

Omit per-work rows when only summary metrics are needed:

```powershell
php artisan nexus:screen-compare `
  --project=tomatomap_label_efficiency `
  --baseline-run=rules-run-id `
  --candidate-run=human-run-id `
  --no-rows `
  --json
```

## Stage Values

Allowed stages:

- `title_abstract`
- `full_text`
- `human_adjudication`

If `--stage` is omitted, core compares all decisions available in both runs. Use a stage filter when comparing runs that should be evaluated at a specific review phase.

## Interpretation

- `comparable_total` counts works that appear in both runs after stage filtering.
- `agreement_count` counts same-decision pairs.
- `disagreement_count` counts changed decisions.
- `transition_counts` groups changes such as `exclude -> include`.
- `missing_in_baseline` and `missing_in_candidate` identify run coverage gaps.
- `reference_run_id` is populated when one run is human, but the comparison algorithm remains generic.

## Practical Workflow

1. Lock the corpus before screening/adjudication.
2. Run deterministic, LLM, council, or human screening workflows.
3. Discover persisted run IDs with `--list-runs`.
4. Compare candidate runs against a human run or previous accepted baseline.
5. Use `--json --no-rows` for compact summary artifacts, and omit `--no-rows` when you need per-work disagreement inspection.
