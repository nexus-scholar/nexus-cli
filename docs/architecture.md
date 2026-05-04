# Architecture

## Folder Structure

nexus-cli/
├── app/Console/Commands/     ← all Artisan commands live here
├── config/nexus.php          ← single config file for all settings
├── docs/
│   ├── AGENT.md              ← agent entry point
│   ├── architecture.md       ← this file
│   ├── commands.md           ← command specs
│   ├── wiki-schema.md        ← wiki format
│   ├── data-sources.md       ← file paths
│   └── wiki/                 ← LLM-maintained research wiki
│       ├── SCHEMA.md
│       ├── index.md
│       ├── log.md
│       ├── papers/
│       ├── concepts/
│       └── synthesis/
├── resources/queries/
│   └── thesis-queries.yml    ← search queries for literature
└── storage/runs/             ← JSON output from search runs

## Data Flow

thesis-queries.yml
    ↓  nexus:search
storage/runs/{id}_{timestamp}.json
    ↓  nexus:ingest {run-file}
docs/wiki/papers/{slug}.md       ← agent writes one page per paper
docs/wiki/concepts/{topic}.md    ← agent updates concept pages
docs/wiki/index.md               ← agent updates catalog
docs/wiki/log.md                 ← agent appends one line
    ↓  nexus:wiki-build
docs/wiki/synthesis/paper1-related-work.md

## Design Rules

1. Every command reads config from config/nexus.php — no hardcoded paths.
2. Run files use timestamp naming: {id}_{YYYYMMDD_HHiiss}.json
3. A stable pointer file storage/runs/latest.json always points to
   the most recent master run. Commands read this instead of globbing.
4. The wiki folder is append-friendly — commands never delete wiki files.
5. All commands must work without a database (flat files only).
