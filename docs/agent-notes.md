# Agent Development Notes

*This file contains technical context, package quirks, and progress state to help AI agents resume development without needing full session history.*

## Architecture & Core Decisions
- **Project Goal:** Build a CLI tool (`nexus-cli`) to manage a flat-file research wiki for a PhD thesis on tomato instance segmentation.
- **Engine:** We are using `nexus-scholar/core:dev-master` (Hexagonal/DDD architecture) instead of the older `nexus-php`.
- **Database:** The app uses an SQLite database strictly because `nexus-scholar/core` requires it to manage internal states (like project locks and migration tables). However, the actual deliverable of the CLI is **flat files** (`docs/wiki/` and `storage/runs/`).

## Known Quirks & Workarounds in `nexus-scholar/core`

### 1. The Caching Bug (`TypeError` in `LaravelSearchCache`)
- **The Issue:** When `SearchAggregator` finishes a search, it tries to cache a complex payload containing DTOs (`['works' => [...], 'stats' => [...]]`). However, `LaravelSearchCache::serialize()` strictly expects an array of `ScholarlyWork` domain objects. This causes a fatal `TypeError` when running `nexus:search`.
- **The Workaround:** Do **not** try to fix the package code. Instead, we have bound an anonymous `NullSearchCache` class to `Nexus\Search\Domain\Port\SearchCachePort` inside our `AppServiceProvider`. This safely bypasses caching entirely, allowing searches to complete without crashing.

### 2. Author Name Retrieval
- **The Issue:** `ScholarlyWork->authors()->all()` returns `Author` value objects.
- **The Fix:** Do not use `$author->name`. The correct method to get the string representation is `$author->fullName()`.

## Development State (May 2026)

### Completed Commands
1. `nexus:wiki-init`: Fully idempotent. Creates `docs/wiki/` folder structure and seeds `index.md`, `log.md`, and `SCHEMA.md` based on `docs/wiki-schema.md`.
2. `nexus:status`: Reads `config/nexus.php`, calculates the current thesis week, safely checks `storage/baseline.json` and `storage/runs/latest.json`, and outputs a table showing wiki health.
3. `nexus:search`: Wraps `SearchAcrossProvidersHandler`. Successfully parses `resources/queries/thesis-queries.yml`, queries providers concurrently, deduplicates, and saves runs to `storage/runs/{id}_{timestamp}.json` and `storage/runs/all_{timestamp}.json`. Updates `latest.json` pointer.

### Next Up: `nexus:ingest`
- **Goal:** Read the latest run JSON, iterate through the papers, and generate `docs/wiki/papers/{slug}.md` files using the template in `docs/wiki-schema.md`.
- **Constraint:** Must never overwrite existing pages. Must update `docs/wiki/log.md`.
