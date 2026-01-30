# Symfony AI HybridStore Demo

Demo application showcasing **Symfony AI HybridStore** for PostgreSQL - combining semantic search, full-text search, and fuzzy matching using the RRF (Reciprocal Rank Fusion) algorithm.

## Features

- **Semantic Search** via pgvector (cosine similarity)
- **Full-text Search** via BM25 or native PostgreSQL FTS
- **Fuzzy Matching** via pg_trgm (typo tolerance)
- **RRF Algorithm** for optimal result ranking
- **31,944 movies** dataset from TMDb

## Architecture

```
┌─────────────────────────────────────────────────────┐
│              Symfony 7.3 Application                │
│                                                     │
│  ┌───────────────────────────────────────────────┐ │
│  │          Symfony AI HybridStore               │ │
│  │                                               │ │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────┐ │ │
│  │  │   pgvector  │ │  BM25/FTS   │ │ pg_trgm │ │ │
│  │  │  (semantic) │ │  (keywords) │ │ (fuzzy) │ │ │
│  │  └──────┬──────┘ └──────┬──────┘ └────┬────┘ │ │
│  │         └───────────────┼─────────────┘      │ │
│  │                         ▼                    │ │
│  │              RRF (Rank Fusion)               │ │
│  └───────────────────────────────────────────────┘ │
│                         │                           │
│  ┌──────────────────────▼──────────────────────┐   │
│  │         Ollama (nomic-embed-text)           │   │
│  │         768-dimensional embeddings          │   │
│  └─────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘

Database: PostgreSQL + pgvector (movies table with 31,944 entries)
```

## Quick Start

### Prerequisites

- Docker and Docker Compose
- PHP 8.2+
- Composer
- Symfony CLI (optional)

### 1. Start Services

```bash
# Clone the repository
git clone https://github.com/ahmed-bhs/symfony-ai-hybrid-search-demo.git
cd symfony-ai-hybrid-search-demo

# Start PostgreSQL and Ollama
docker compose up -d

# Wait for services to be ready
docker compose ps
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Setup Store and Import Data

```bash
# Setup the store (creates table + installs pgvector, pg_trgm, BM25 functions)
php bin/console ai:store:setup ai.store.postgres.movies

# Import movies (BM25 index is created automatically on first import)
# Quick test (1000 movies)
php bin/console app:import-movies --limit=1000 --batch-size=50

# Full dataset (~40 minutes)
php bin/console app:import-movies --batch-size=50
```

### 4. Start the Server

```bash
symfony server:start
# or
php -S localhost:8000 -t public/
```

### 5. Test the Search

```bash
# BM25 search (recommended)
curl "http://localhost:8000/api/search/bm25?q=green+ogre&limit=5"

# Native PostgreSQL FTS
curl "http://localhost:8000/api/search/native?q=green+ogre&limit=5"

# Compare both strategies
curl "http://localhost:8000/api/compare?q=green+ogre&limit=5"
```

## Text Search Strategies

### BM25 (Recommended)

Uses `plpgsql_bm25` for industry-standard relevance ranking.

```bash
curl "http://localhost:8000/api/search/bm25?q=green+ogre&limit=5"
```

**Pros:**
- More accurate relevance ranking
- Better understanding of term frequency and document length
- Same algorithm as Elasticsearch, Lucene, Meilisearch

### Native PostgreSQL FTS

Uses PostgreSQL's built-in `ts_rank_cd` function.

```bash
curl "http://localhost:8000/api/search/native?q=green+ogre&limit=5"
```

**Pros:**
- No additional functions needed
- Works with any PostgreSQL installation

### Comparison: "green ogre" Query

| Rank | BM25 | Native FTS |
|------|------|------------|
| 1 | **Shrek** (42.0) | The Green Mile (16.8) |
| 2 | Gremlins 2 (33.9) | Fried Green Tomatoes (15.4) |
| 3 | Fried Green Tomatoes (25.8) | Greed (9.5) |

BM25 correctly identifies Shrek because it understands that "green ogre" matches the overview: *"It ain't easy bein' green -- especially if you're a likable (albeit smelly) ogre named Shrek."*

## API Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /api/search/bm25?q=query` | Search using BM25 (recommended) |
| `GET /api/search/native?q=query` | Search using native PostgreSQL FTS |
| `GET /api/compare?q=query` | Compare BM25 vs Native FTS side-by-side |
| `GET /api/search?q=query` | Default search (uses BM25) |

### Response Format

```json
{
  "query": "matrix",
  "strategy": "BM25 (plpgsql_bm25 extension)",
  "hits": 10,
  "processingTimeMs": 120,
  "results": [
    {
      "title": "The Matrix",
      "overview": "A computer hacker learns about the true nature of reality...",
      "genres": ["Action", "Science Fiction"],
      "score": 85.5,
      "score_breakdown": {
        "vector_rank": 1,
        "fts_rank": 1,
        "vector_contribution": 45.2,
        "fts_contribution": 30.1,
        "fuzzy_contribution": 10.2
      }
    }
  ]
}
```

## Configuration

### config/packages/symfony_ai.yaml

```yaml
ai:
    platform:
        ollama:
            host_url: 'http://127.0.0.1:11434'

    store:
        postgres:
            movies:
                dsn: 'pgsql:host=localhost;dbname=hybrid_search'
                username: 'postgres'
                password: 'postgres'
                table_name: 'movies'
                vector_field: 'embedding'
                distance: cosine

                hybrid:
                    enabled: true
                    content_field: 'content'
                    semantic_ratio: 0.3          # 30% semantic, 70% text search
                    language: 'english'
                    bm25_language: 'en'
                    text_search_strategy: 'bm25' # or 'native'
                    rrf_k: 10
                    normalize_scores: true
                    fuzzy_weight: 0.4

    vectorizer:
        ollama:
            model: 'nomic-embed-text'
```

### Key Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `semantic_ratio` | Balance between vector (1.0) and text (0.0) | 0.3 |
| `text_search_strategy` | `bm25` or `native` | `bm25` |
| `rrf_k` | RRF constant for rank fusion | 60 |
| `fuzzy_weight` | Weight of fuzzy matching (0.0-1.0) | 0.5 |
| `normalize_scores` | Normalize scores to 0-100 | true |

## How It Works

### 1. Setup Phase (`ai:store:setup`)

- Creates the `movies` table with vector column
- Installs `pgvector` and `pg_trgm` extensions
- Installs BM25 functions (`bm25topk`, `bm25createindex`, etc.)

### 2. Import Phase (`app:import-movies`)

- Generates embeddings via Ollama (nomic-embed-text)
- Inserts documents into the table
- **Automatically creates BM25 index** on first batch (lazy indexing)

### 3. Search Phase

1. Query text is vectorized via Ollama
2. Three parallel searches are executed:
   - **Vector search** (pgvector cosine similarity)
   - **Text search** (BM25 or ts_rank_cd)
   - **Fuzzy search** (pg_trgm word similarity)
3. Results are merged using **RRF (Reciprocal Rank Fusion)**
4. Final scores are normalized to 0-100 range

## Commands

```bash
# Setup store
php bin/console ai:store:setup ai.store.postgres.movies

# Import movies
php bin/console app:import-movies --limit=1000 --batch-size=50

# Database access
docker exec -it postgres_hybrid_search psql -U postgres -d hybrid_search

# Check BM25 index
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search \
  -c "SELECT * FROM movies_content_bm25i_params LIMIT 3;"

# Service logs
docker logs -f postgres_hybrid_search
docker logs -f ollama_embeddings
```

## Troubleshooting

### PostgreSQL Connection Issues

```bash
# Check if container is running
docker compose ps

# Check logs
docker logs postgres_hybrid_search
```

### Ollama Issues

```bash
# Check if model is loaded
docker exec ollama_embeddings ollama list

# Re-download model
docker exec ollama_embeddings ollama pull nomic-embed-text
```

### BM25 Not Working

```bash
# Check if BM25 functions exist
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search \
  -c "SELECT proname FROM pg_proc WHERE proname = 'bm25topk';"

# Check if BM25 index exists
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search \
  -c "SELECT * FROM movies_content_bm25i_params LIMIT 1;"
```

## Documentation

- [Symfony AI](https://github.com/symfony/ai)
- [pgvector](https://github.com/pgvector/pgvector)
- [plpgsql_bm25](https://github.com/jankovicsandras/plpgsql_bm25)
- [RRF Algorithm Paper](https://plg.uwaterloo.ca/~gvcormac/cormacksigir09-rrf.pdf)
- [Ollama](https://ollama.ai/)

## Dataset

**Source:** 31,944 movies from TMDb

**Fields:**
- title, overview, genres
- release_date, poster
- TMDb metadata (keywords, cast, director)

**Enrichments:**
- Vector embeddings (768 dimensions via nomic-embed-text)
- BM25 full-text index
- Trigram indexes for fuzzy search

## License

MIT
