# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Project Overview

"Assistant" — a web-based music collection management tool. Browse, search, and organize a music library; find similar tracks via audio similarity (Musly) and metadata (BPM, key, genre, year); edit ID3 tags; extract audio features (Essentia); retrieve track info from Beatport; build and rearrange DJ mixes.

## Tech Stack

- **Backend:** PHP 8.5, Slim Framework 4 (PSR-15), PHP-DI (autowiring)
- **Frontend:** Twig 3, jQuery/Bootstrap 5/Tabler (general), React 19 (Mix module), esbuild
- **Database:** MongoDB (native PHP extension)
- **CLI:** Symfony Console 5.3
- **Infrastructure:** Docker (PHP-FPM, Nginx, MongoDB, music-classifier, music-similarity services)


## Agent Operating Principles

- Prefer modifying existing code over introducing new abstractions.
- Do not introduce new frameworks, libraries, or architectural patterns without explicit instruction.
- Follow existing module boundaries strictly (no cross-module shortcuts).
- Keep changes minimal and localized unless a refactor is explicitly requested.
- When in doubt: align with existing patterns in the same module.
- Never load or analyze entire files unless necessary — prefer targeted reads.
- Never propose refactors of unrelated code.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.

## Commands

### Build frontend

```bash
yarn build # production build → public/js/mix.dist.js
yarn watch # dev watch mode with source maps
```

### Run tests

```bash
./vendor/bin/phpunit
./vendor/bin/phpunit tests/path/To/TestFile.php
```

### Run CLI tasks

```bash
php bin/console.php <command>
```

Key commands:
- AudioDataCalculatorTask
- IndexerTask
- CleanerTask
- CollectionAnalysisTask
- SimilarTracksCollectionReIndexerTask

### Docker

```bash
docker-compose up
```

- Docker is the default development environment.
- Do not assume local PHP/Node installations.
- External services (similarity, classifier) must be treated as network dependencies.

### Dependencies

```bash
composer install
yarn install
```

## Architecture

### Entry Points

- **Web:** `public/index.php`
- **CLI:** `bin/console.php`

### Configuration (`config/`)

- `config.inc` — app settings
- `container.inc` — DI definitions
- `routes.inc` — route definitions
- `middleware.inc` — middleware stack
- `tasks.inc` — CLI commands

### Module Structure (`src/Assistant/Module/`)

All source lives under `src/Assistant/` and follows Composer autoloading (PSR-4 unless explicitly stated otherwise).

Modules:

- Collection
- Track
- Mix
- Search
- Directory
- Dashboard
- Common

Layering:

Controller → Service → Repository

## Key Subsystems

### Track Similarity

Multi-provider system combining Musly audio similarity with metadata matching.

### Audio Data

Essentia-based BPM, key, and feature extraction.

### Mix Frontend

React 19 app with hooks:
- useMixApi
- useKeyboardShortcuts
- useDragReorder

## Data Flow

Controllers → Services → Repositories/Extensions → Response (Twig/JSON)

### Error Handling

- Services throw domain-specific exceptions.
- Controllers map exceptions to HTTP responses.
- Never return partially valid data structures.

## Coding Conventions

### PHP

- Use `final readonly class` where possible.
- Use typed constants.
- DI via PHP-DI autowiring.
- Follow PER Coding Style 3.0.
- Avoid redundant comments.

### Immutability & DTOs

- Domain models are immutable.
- DTOs may be mutable if used for transport.
- No domain logic in DTOs.
- Never expose BSON structures outside repositories.

## API Contracts

- Avoid duplicate identifiers.
- Shape responses in DTO `toJson()` methods.
- Prefer fixing API over adding frontend null checks.
- API must be structurally stable.
- Use explicit flags instead of implicit meaning.

## React (Mix Module)

- Use hooks for logic separation.
- Keep components presentational when possible.
- Avoid global state.
- Use dedicated API hooks.
- Do not duplicate backend logic.

## Slim Framework

- Use `$request->getAttribute()` for route params.
- Invalid params → redirect, not 404.
- Register static routes before dynamic.

## Twig Templates

- Use `{% include ... only %}` to isolate scope.

## Testing

- Unit test services with logic.
- Avoid testing trivial controllers.
- Use fixtures for MongoDB instead of mocking BSON.

## Naming

- Update all layers when renaming.
- Avoid generic names (`data`, `item`, etc.).
- Avoid unnecessary abbreviations.

## Adding New Features

1. Identify module
2. Add/extend:
    - Controller
    - Service
    - Repository
3. Update DI container
4. Update routes
5. Update DTOs (if API)
6. Update UI (Twig/React)

## Common Pitfalls

- MongoDB returns BSON types — convert explicitly.
- Do not add aggregation stages with null values.
- Perform data enrichment at processing time, not rendering.
- Avoid key collisions when spreading arrays.
- Avoid heavy computations in HTTP lifecycle.
- Avoid synchronous external calls in user-facing endpoints.
- Ensure backward compatibility for MongoDB schema.

## Token Efficiency

- Do not re-read files unnecessarily.
- Do not re-run commands without reason.
- Batch edits into single operations.
- Avoid unnecessary summaries.
