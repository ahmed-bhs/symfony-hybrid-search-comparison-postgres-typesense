# Symfony AI HybridStore Demo

Demo showcasing **Symfony AI HybridStore** for PostgreSQL - combining semantic search, full-text search (BM25/native), and fuzzy matching via RRF.

## Architecture

```
┌───────────────────────────────────────────┐
│       Symfony AI HybridStore              │
│                                           │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐     │
│  │pgvector │ │BM25/FTS │ │ pg_trgm │     │
│  │(vector) │ │ (text)  │ │ (fuzzy) │     │
│  └────┬────┘ └────┬────┘ └────┬────┘     │
│       └───────────┼───────────┘          │
│                   ▼                       │
│         RRF (Rank Fusion)                │
└───────────────────────────────────────────┘
```

## Quick Start

```bash
# Start services
docker compose up -d

# Install dependencies
composer install

# Setup store & import data
php bin/console ai:store:setup ai.store.postgres.movies
php bin/console app:import-movies --limit=1000 --batch-size=50

# Start server
symfony server:start
```

## Test

```bash
curl "http://localhost:8000/api/search/bm25?q=green+ogre&limit=5"
curl "http://localhost:8000/api/search/native?q=green+ogre&limit=5"
```

## API Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /api/search/bm25?q=query` | BM25 search (recommended) |
| `GET /api/search/native?q=query` | Native PostgreSQL FTS |
| `GET /api/compare?q=query` | Compare both strategies |

## Links

- [Symfony AI](https://github.com/symfony/ai)
- [pgvector](https://github.com/pgvector/pgvector)
- [plpgsql_bm25](https://github.com/jankovicsandras/plpgsql_bm25)
