# Artisan Command Specifications

## nexus:status  (EXISTS — needs real output)

Shows live research dashboard.

Output:
- Dataset: labeled count, unlabeled count
- Latest baseline result (reads storage/baseline.json if exists)
- Current week derived from project start date in config
- Latest run file name and paper count
- Wiki health: page count in papers/, concepts/, synthesis/

Reads from:
- config/nexus.php
- storage/baseline.json
- storage/runs/latest.json
- docs/wiki/ (counts only)

---

## nexus:run-stats  (BUILD)

Signature: nexus:run-stats {run? : path to run JSON, defaults to latest}

Shows quick stats for a run JSON:
- total count
- counts by provider
- counts by query_id (if present)

Reads from:
- storage/runs/latest.json (default)
- run file path (optional argument)

---

## nexus:search  (BUILD)

Signature: nexus:search {--id= : run specific query} {--all : run all queries}

Runs queries from resources/queries/thesis-queries.yml against
all enabled providers via NexusSearcher (nexus-php).

Deduplicates results. Saves:
- storage/runs/{id}_{timestamp}.json  (per query)
- storage/runs/all_{timestamp}.json   (global deduped master)
- storage/runs/latest.json            (stable pointer)

Displays: provider counts, raw vs dedup counts, saved file path.

---

## nexus:ingest {file?}  (BUILD)

Signature: nexus:ingest {file? : path to run JSON, defaults to latest}

Reads documents from a run JSON file. For each document, outputs
a pre-filled paper page template to docs/wiki/papers/{slug}.md
IF the file does not already exist (never overwrites).

The agent (not this command) fills in the analysis fields.
This command only creates the scaffolded file with frontmatter
pre-filled from the document metadata (title, year, doi, authors).

Also appends an entry to docs/wiki/log.md.

---

## nexus:wiki-init  (BUILD)

Creates the docs/wiki/ folder structure and seed files if they
do not exist:
- docs/wiki/SCHEMA.md      (from wiki-schema.md template)
- docs/wiki/index.md       (empty catalog)
- docs/wiki/log.md         (empty log with header)
- docs/wiki/papers/        (empty dir with .gitkeep)
- docs/wiki/concepts/      (empty dir with .gitkeep)
- docs/wiki/synthesis/     (empty dir with .gitkeep)

Idempotent — safe to run multiple times.

---

## nexus:wiki-status  (BUILD)

Counts pages in each wiki section.
Lists any papers/ pages where thesis_relevance is empty (not yet analyzed).
Lists synthesis/ pages and their word counts.
Flags if index.md is out of sync (page exists but not in index).

---

## nexus:export {--format=bibtex}  (BUILD LATER)

Exports all papers in docs/wiki/papers/ that have a doi field
to the requested format (bibtex, csv, ris).
Output: storage/exports/{format}_{timestamp}.{ext}

Build this only after nexus:ingest is working.

---

## nexus:screen  (EXISTS)

Signature:

```text
nexus:screen
  {run? : path to run JSON, defaults to latest}
  {--criteria= : path to criteria JSON/YAML}
  {--project= : project ID for database-backed core screening}
  {--include=* : inclusion criterion for database-backed core screening}
  {--exclude=* : exclusion criterion for database-backed core screening}
  {--mode=llm : llm|council for database-backed core screening}
  {--stage=title_abstract : screening stage for database-backed core screening}
  {--model= : single model for database-backed core screening}
  {--council-models= : comma-separated council model IDs}
  {--max= : maximum persisted works to screen}
  {--work-ids= : comma-separated internal work IDs}
  {--query-ids= : comma-separated search query IDs}
  {--name= : human-readable screening run name}
  {--store-prompts : persist rendered prompts in screening_votes}
  {--store-raw-responses : persist raw LLM responses in screening_votes}
```

Modes:

- Run-file mode screens local run JSON and writes `storage/screens/{run_id}.json`.
- Project mode delegates to `nexus-scholar/core` and persists `screening_runs`, `screening_decisions`, and `screening_votes`.

See `docs/commands/nexus-screen/README.md` for LLM setup, council examples, and DB inspection commands.

---

## nexus:screen-adjudicate  (EXISTS)

Signature:

```text
nexus:screen-adjudicate
  {--project= : project ID}
  {--actor= : reviewer/user ID}
  {--file= : YAML/JSON adjudication file}
  {--run= : existing or desired human screening run ID}
  {--stage= : screening stage override}
  {--criteria-hash= : criteria hash override}
  {--name= : human-readable adjudication run name}
  {--example : print an example YAML adjudication file and exit}
```

Parses a human adjudication file and delegates to `nexus-scholar/core`.

Core owns:
- locked-project requirement,
- project work membership checks,
- decision persistence,
- run metadata,
- latest-decision ordering.

See `docs/commands/nexus-screen-adjudicate/README.md`.

---

## nexus:screen-compare  (EXISTS)

Signature:

```text
nexus:screen-compare
  {--project= : project ID}
  {--baseline-run= : baseline screening run ID}
  {--candidate-run= : candidate screening run ID}
  {--stage= : optional stage filter}
  {--json : output JSON}
  {--no-rows : omit per-work rows from result}
```

Compares two persisted screening runs through `nexus-scholar/core` and prints agreement, disagreement, transition counts, missing rows, and optional JSON.

See `docs/commands/nexus-screen-compare/README.md`.

---

## nexus:fetch-pdfs  (BUILD)

Signature: nexus:fetch-pdfs {screen? : path to screen JSON, defaults to latest}

Fetches PDFs for included papers (OpenAlex) and saves:
- storage/pdfs/{run_id}/{slug}.pdf
- storage/pdfs/{run_id}/manifest.json
