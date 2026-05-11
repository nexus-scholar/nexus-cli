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

## nexus:screen  (BUILD)

Signature: nexus:screen {run? : path to run JSON, defaults to latest} {--criteria= : path to criteria JSON}

Screens run results using inclusion/exclusion criteria and writes:
- storage/screens/{run_id}.json

---

## nexus:fetch-pdfs  (BUILD)

Signature: nexus:fetch-pdfs {screen? : path to screen JSON, defaults to latest}

Fetches PDFs for included papers (OpenAlex) and saves:
- storage/pdfs/{run_id}/{slug}.pdf
- storage/pdfs/{run_id}/manifest.json

