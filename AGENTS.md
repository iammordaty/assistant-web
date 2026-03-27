# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Project Overview

"Assistant" — a web-based music collection management tool. Browse, search, and organize a music library; find similar tracks via audio similarity (Musly) and metadata (BPM, key, genre, year); edit ID3 tags; extract audio features (Essentia); retrieve track info from Beatport; build and rearrange DJ mixes.

## Tech Stack

- **Backend:** PHP 8.5, Slim Framework 4 (PSR-15), Twig 3, PHP-DI (autowiring)
- **Frontend:** jQuery/Bootstrap 5/Tabler, React 19 (Mix module), esbuild
- **Database:** MongoDB (via native PHP extension)
- **CLI:** Symfony Console 5.3
- **Infrastructure:** Docker (PHP-FPM, Nginx, MongoDB, music-classifier, music-similarity services)

## Commands

### Build frontend

```bash
yarn build          # production build → public/js/mix.dist.js
yarn watch          # dev watch mode with source maps
```

### Run tests

```bash
./vendor/bin/phpunit                    # all tests
./vendor/bin/phpunit tests/path/To/TestFile.php   # single test file
```

### Run CLI tasks

```bash
php bin/console.php <command>
# Key commands: AudioDataCalculatorTask, IndexerTask, CleanerTask,
# CollectionAnalysisTask, SimilarTracksCollectionReIndexerTask
```

### Docker

```bash
docker-compose up   # full stack: PHP-FPM, Nginx, MongoDB, Node builder, music services
```

### Dependencies

```bash
composer install    # PHP
yarn install        # Node/frontend
```

## Architecture

### Entry Points

- **Web:** `public/index.php` — Slim app bootstrapped with PHP-DI bridge
- **CLI:** `bin/console.php` — Symfony Console with shared DI container

### Configuration (config/)

- `config.inc` — app settings: DB connection, collection paths, similarity providers, API credentials
- `container.inc` — PHP-DI definitions (services, repositories, controllers)
- `routes.inc` — all Slim route definitions
- `middleware.inc` — middleware stack (Twig, error handling)
- `tasks.inc` — CLI command registration

### Module Structure (src/Assistant/Module/)

All source lives under `src/Assistant/` with PSR-0 autoloading. The app is split into 7 modules, each following a consistent Controller → Service → Repository layering:

- **Collection** — indexing, file reading, validation of the music library
- **Track** — individual track management, ID3 editing, similarity engine, metadata, audio features
- **Mix** — DJ mix creation/management (React frontend + API controllers)
- **Search** — track/directory search, random playlist generation
- **Directory** — directory browsing and management
- **Dashboard** — dashboard views
- **Common** — shared infrastructure: logging, config, API clients (Beatport, Google), Twig extensions

### Key Subsystems

**Track Similarity** (`Module/Track/Extension/TrackSimilarity/`): Multi-provider system combining Musly audio similarity with metadata matching (BPM, key, genre, year). Configurable weights and thresholds.

**Audio Data** (`Module/Track/Extension/TrackAudioData/`): Wraps Essentia music extractor for BPM detection, key analysis, and audio feature extraction.

**Mix Module Frontend** (`public/js/src/`): React 19 app with custom hooks (`useMixApi`, `useKeyboardShortcuts`, `useDragReorder`), modal-based UI, drag-and-drop track reordering. Built with esbuild (`build.js`).

### Data Flow

Controllers receive PSR-7 requests → delegate to Services → Services use Repositories (MongoDB) and Extensions (similarity, audio analysis, external APIs) → responses rendered via Twig templates or returned as JSON (Mix API).

## Coding Conventions

### PHP

- All classes: `final readonly class` (or `final class` for stateful).
- Typed constants: `private const int`, `private const array` (PHP 8.3+ syntax).
- DI: PHP-DI auto-wiring for controllers. Services/repos registered in `config/container.inc`.
- Console tasks use manual `factory(ContainerInterface)` pattern.
- PER Coding Style 3.0. No comments narrating what code does.

### Slim Framework

- Route parameters: use `$request->getAttribute('paramName')`, NOT `$args['paramName']`.
- Invalid route parameter → redirect to parent page (e.g. index), NOT 404.
- Route ordering: register static paths before dynamic (e.g. `/status/toggle-ignore` before `/status/{type}`).

### Twig Templates

- Partial templates included via `{% include ... only %}` to isolate scope.

### Naming & Renaming

When renaming a concept across the codebase, update ALL layers consistently:
1. PHP enum case
2. URL slug
3. MongoDB stored string
4. UI label
5. Twig template comparisons
6. Related class and file names
7. Registration/factory calls

Descriptive boolean flags within data structures (e.g. `is_low_bitrate`) can keep their specific names — they describe a condition, not a category.

## Common Pitfalls

- MongoDB returns `BSONDocument`/`BSONArray`, not plain PHP arrays. Always convert when needed.
- When building aggregation pipelines, don't add stages with `null` values (e.g. `['$limit' => null]`). Only add optional stages when their value is set.
- Data enrichment (adding related data to stored records) should happen at processing/storage time, not at render time. Templates should receive all data they need without extra lookups.
- When using the spread operator (`...$array`) to pass data to templates, ensure keys don't collide between the spread array and individually set variables.
