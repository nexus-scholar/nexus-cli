# Agent Entry Point — nexus-cli

Read this file first. Then read the files listed under "Reading Order".

## What This App Is

`nexus-cli` is a Laravel CLI application that drives a personal
research wiki for a PhD thesis on tomato instance segmentation.

It has two jobs:
1. Help retrieve and organize academic literature (via nexus-scholar/core).
2. Maintain a persistent LLM-written wiki in `docs/wiki/`.

The wiki is the deliverable. The CLI commands are the tools to build it.

## Reading Order

1. `docs/agent-notes.md`    — current progress, bugs, and context
2. `docs/architecture.md`   — system design, folder structure
3. `docs/commands.md`       — Artisan command specs (build these)
4. `docs/wiki-schema.md`    — wiki format and conventions
5. `docs/data-sources.md`   — file paths and naming rules

## Tech Stack

- PHP 8.3 / Laravel 13
- SQLite (already configured)
- nexus-scholar/core — Hexagonal/DDD scholarly research engine
- laravel/prompts — interactive terminal UI
- Pest — testing

## Current State

- `nexus:status` command exists (scaffold only, needs real output)
- No other commands exist yet
- `docs/wiki/` does not exist yet — agent must create the folder
  structure and seed files on first run

## What the Agent Should Never Do

- Do not modify `docs/wiki/` content directly (that is the LLM wiki
  layer, maintained separately)
- Do not add web routes, controllers, or views
- Do not add a database beyond SQLite
- Do not install packages not listed in commands.md
