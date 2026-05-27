# Agent Development Notes

*This file contains technical context, package quirks, and progress state to help AI agents resume development without needing full session history.*

## Architecture & Core Decisions
- **Project Goal:** Provide a Laravel Artisan host for Nexus Scholar systematic-review workflows, including local run files/wiki notes and DB-backed project workflows through `nexus-scholar/core`.
- **Engine:** The app consumes `nexus-scholar/core:^0.2` during pre-1.0 stabilization. Keep host commands thin around core handlers and reader ports.
- **Database:** SQLite is the default local store for core migrations, project locks, corpus snapshots, screening runs, full-text fetch audits, job lifecycle records, and export history.
- **1.0 boundary:** `core` owns reusable handlers, ports, migrations, config, and package commands. This app owns product-specific CLI presentation and local workflow files.

## Current Core Posture

- Provider integration tests in `core` are fixture-backed and should not call live providers in CI.
- Core exposes host reader ports for export history, job lifecycle, and full-text fetch artifacts. Use those ports instead of direct SQL from CLI commands.
- Published provider config should not contain placeholder credentials or contact emails; hosts set real values in `.env`.
- Dependency advisories are part of the release gate through `composer audit --format=plain`.

## Development State (May 2026)

### Completed Command Groups
1. Workspace/run-file commands: `nexus:wiki-init`, `nexus:status`, `nexus:run-stats`, `nexus:ingest`.
2. Search and screening commands: `nexus:search`, `nexus:screen`, `nexus:screen-adjudicate`, `nexus:screen-compare`.
3. Corpus and export commands: `nexus:corpus-lock`, `nexus:export-bibliography`, `nexus:exports`.
4. Full-text and graph commands: `nexus:fetch-full-text`, `nexus:fetch-pdfs`, `nexus:full-text-artifacts`, `nexus:graph`.
5. Job lifecycle read command: `nexus:jobs`.

### Next Up
- Add snowballing commands once the core snowballing workflow is ready for host use.
- Add an end-to-end fixture test that covers search -> screen -> lock -> adjudicate -> compare -> full text -> graph -> export.
- Decide whether this remains a project template or becomes an installable CLI package.
