# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Project Overview

"Assistant" — a private, self-hosted web-based music collection management tool for personal home DJ-ing and music library management. It is used exclusively for individual home use and allows browsing, searching, and organizing a personal music library; finding similar tracks via audio similarity (Musly) and metadata analysis (BPM, key, genre, year); editing ID3 tags; extracting audio features (Essentia); retrieving track information from Beatport; and building and rearranging DJ mixes.

The project is developed and maintained as a personal application, not as a commercial product or multi-user service. Solutions should prioritize clarity, maintainability, and modern software engineering practices. Code should be clean, elegant, easy to understand, and structured in a way that supports future development and experimentation.

Performance is important, but it is a secondary priority. Prefer readable and robust implementations over premature optimization. Optimize only when there is a demonstrated need or measurable bottleneck.

## Agent Operating Principles

- Prefer modifying existing code over introducing new abstractions.
- Do not introduce new frameworks, libraries, or architectural patterns without explicit instruction.
- Follow existing module boundaries strictly (no cross-module shortcuts).
- Keep changes minimal and localized unless a refactor is explicitly requested.
- When in doubt: align with existing patterns in the same module.
- Never load or analyze entire files unless necessary — prefer targeted reads.
- Never propose refactors of unrelated code.

## Tech Stack

- **Backend:** PHP 8.5, Slim Framework 4 (PSR-15), PHP-DI (autowiring)
- **Frontend:** Twig 3, jQuery/Bootstrap 5/Tabler (general), React 19 (Mix module), esbuild
- **Database:** MongoDB (native PHP extension)
- **CLI:** Symfony Console 5.3
- **Infrastructure:** Docker (PHP-FPM, Nginx, MongoDB, music-classifier, music-similarity services)

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

## Collection Filesystem Structure

The music library lives under `collection.root_dir` (`/collection`). Only three directories matter to the app (configured in `config/config.inc`); everything else under root (Albums, Compilations, Sets, `@eaDir`, etc.) must be ignored.

- **`/collection/_new`** — incoming (`collection.incoming_dir`). Newly bought/added tracks not yet part of the collection: awaiting tag fixes, renaming, and DB indexing. Treat as a queue.
- **`/collection/_new/_zrobione`** — ready (`collection.ready_dir`). Transitional "done" folder inside incoming, holding tracks already processed (tags fixed / renamed) and ready for further handling. When a track is renamed with the "mark as ready" flag, `TrackRenameService` prepends this dir's basename to the target path, moving the file here. Not indexed as part of the collection.
- **`/collection/Other`** — indexed. **Single tracks** already in the collection (one track picked from an album/single/EP — never a whole release). Flat structure. Filename format: `Artist - Track.mp3` (no track numbers).
- **`/collection/Singles`** — indexed. **Whole releases** (single/EP/maxi/remix pack, 1..N tracks). Nested structure:

```
Singles/<Year>/<Month No> <Month Name>/<Artist>/<Release>/<Artist> - <Track No> - <Title 1>.mp3
Singles/<Year>/<Month No> <Month Name>/<Artist>/<Release>/<Artist> - <Track No> - <Title 2>.mp3
Singles/<Year>/<Month No> <Month Name>/<Artist>/<Release>/<Artist> - <Track No> - <Title 3>.mp3
# ...

# or

Singles/<Year>/<Month No> <Month Name>/<Artist>/<Release>/<Track No>. <Artist 1> - <Title 1>.mp3
Singles/<Year>/<Month No> <Month Name>/<Artist>/<Release>/<Track No>. <Artist 1> feat. <Artist 2> - <Title 2>.mp3
Singles/<Year>/<Month No> <Month Name>/<Artist>/<Release>/<Track No>. <Artist 1> feat. <Artist 3> - <Title 3>.mp3
# ...

# where Month No is as 1-based, zero-padded month number, Month No - lowercased month full name in polish, ie:
# 01. styczeń
# 04. kwiecień
# 09. wrzesień
# 12. grudzień
```

Two valid filename formats in `Singles`:
- Single artist for whole release (most common): `Artist - NN - Track.mp3` (track number mandatory).
- Various artists within a release: `NN. Artist - Track.mp3` (leading number preserves release order intentionally).

### Location rules & gotchas

- Location type is resolved by `TrackLocationArbiter` against `collection.indexed_dirs` / `incoming_dir` / `ready_dir`, **not** by `root_dir` (a path under root but outside indexed dirs is `UNSUPPORTED`, not in-collection). `LocationKind` is a **pure classification** enum; the per-location filename format and base-dir policy live in `TrackRenameService` (which also has the track context needed to pick the right `Singles` variant).
- `ready_dir` is nested inside `incoming_dir`, so the arbiter checks it **first** (most-specific wins) and maps it to `LocationKind::READY`, distinct from `INCOMING`. `isInIncoming()` is true for both (physically under incoming, both outside the collection); `isReady()` is the precise predicate for "in `ready_dir`". Use `isReady()` (not `isInIncoming()`) when selecting processed files to promote into the collection, so raw incoming files are not swept in.
- The same track may exist both as a single in `Other` and as part of a full release in `Singles` — always consider the directory context.
- **There is NO letter directory** (e.g. `Singles/A/...`). Any older comment/test implying a `<letter>` segment is wrong; the segment two levels above the file is `Year/Month`.
- The `Singles` base dir (`TrackRenameService::baseDirFor()`) is `dirname($file->getPath(), 2)` — **positional relative to the file** (the two levels above `Artist/Release`, i.e. `Year/Month`), whose name is preserved verbatim. It is intentionally NOT derived from the artist and NOT relocated when the artist changes (deliberate — see plan item "B4, rezygnacja"). Do not "fix" it to compute a letter from the artist; that would corrupt the real `Year/Month` structure.
- When renaming a `Singles` track (collection edit flow, `TrackRenameService::renameToCollectionLayout()`), the **existing filename pattern is preserved**: single-artist (`Artist - NN - Title`) rebuilds the `Artist/Release` dirs from metadata under `Year/Month`; various-artists (`NN. Artist - Title`, detected by a leading `NN.` prefix) only renames the file in place — the release directory is not rebuilt from a single track's metadata (there is no album-artist concept in the model). CLI `track:rename --format` still takes an explicit format.

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

### Comments & Documentation

- Comments and docs must describe the **current** state of the code, never the history of changes.
- Do not document previous implementations (what changed, what was removed, how it used to work). That belongs in Git.
- When a comment is warranted, explain **why** the code exists, its purpose, and any assumptions or constraints — not what is already obvious from the code.
- Remove comments that merely restate what the code plainly does.

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
