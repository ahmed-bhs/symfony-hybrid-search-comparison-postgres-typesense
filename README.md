# Hybrid Search Comparison: Symfony AI vs Typesense

> [🇫🇷 Version française](README.fr.md) | [📚 Full Documentation](https://ahmed-bhs.github.io/symfony-hybrid-search-comparison-postgres-typesense/)

Compare two hybrid search implementations for a movie database (31,944 movies):
- **Symfony AI HybridStore**: PostgreSQL + pgvector + RRF algorithm
- **Typesense**: Search engine with built-in vector search

Both solutions combine semantic search (embeddings), full-text search (keywords), and fuzzy matching (typos).

## Why This Comparison?

This project demonstrates real-world hybrid search implementations with the same dataset, allowing you to:
- **Compare performance** between PostgreSQL+pgvector and Typesense
- **Understand trade-offs** (flexibility vs. ease of use, cost vs. performance)
- **Choose the right solution** for your use case
- **Learn hybrid search concepts** with working examples

## Quick Comparison

| Feature | Symfony AI HybridStore | Typesense |
|---------|------------------------|-----------|
| **Backend** | PostgreSQL + pgvector | Dedicated search engine |
| **Algorithm** | Custom RRF (Reciprocal Rank Fusion) | Built-in hybrid search |
| **Setup** | More complex (multiple extensions) | Simpler (single service) |
| **Flexibility** | Full SQL access, custom algorithms | API-based, predefined features |
| **Cost** | Free (open source PostgreSQL) | Free (self-hosted) or Cloud |
| **Best for** | Complex queries, existing PostgreSQL | Fast setup, managed solution |

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                 Symfony 7.3 Application                     │
│                                                             │
│  ┌────────────────────────┐  ┌──────────────────────────┐  │
│  │  Symfony AI HybridStore│  │      Typesense           │  │
│  │                        │  │                          │  │
│  │  ┌──────────────────┐ │  │  ┌────────────────────┐  │  │
│  │  │ Vector (pgvector)│ │  │  │  Vector Search     │  │  │
│  │  │ FTS (ts_rank)    │ │  │  │  Full-text Search  │  │  │
│  │  │ Fuzzy (pg_trgm)  │ │  │  │  Fuzzy Matching    │  │  │
│  │  │ RRF Algorithm    │ │  │  │  Built-in Hybrid   │  │  │
│  │  └────────┬─────────┘ │  │  └─────────┬──────────┘  │  │
│  └───────────┼───────────┘  └────────────┼─────────────┘  │
│              │                            │                 │
│  ┌───────────▼────────────────────────────▼──────────────┐ │
│  │              Ollama (nomic-embed-text)                │ │
│  │              Shared embeddings (768 dimensions)       │ │
│  └───────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘

Data:  PostgreSQL (movies table)          Typesense (movies collection)
       31,944 movies with embeddings      31,944 movies with embeddings
```

## Features

### Symfony AI HybridStore
- Custom RRF implementation (configurable weights)
- Direct PostgreSQL access for complex queries
- Full control over ranking algorithm
- Configurable semantic_ratio (0.0 to 1.0)
- Advanced filtering with SQL
- Integrated with Doctrine ORM

### Typesense
- Built-in hybrid search (auto-tuned)
- RESTful API (language-agnostic)
- Auto-generated embeddings
- Built-in typo tolerance
- Faceted search support
- Easier to scale horizontally

## Quick Start

### Prerequisites
- Docker and Docker Compose
- 8GB RAM minimum (16GB recommended)
- 4 CPU cores minimum

### 1. Clone and Setup

```bash
git clone https://github.com/ahmed-bhs/symfony-hybrid-search-comparison-postgres-typesense.git
cd symfony-hybrid-search-comparison-postgres-typesense

# Start all services (PostgreSQL, Typesense, Ollama)
./docker-setup.sh
```

The script will:
- Start PostgreSQL 16 + pgvector (port 5432)
- Start Typesense 27.1 (port 8108)
- Start Ollama with nomic-embed-text (port 11434)
- Verify all services are ready

### 2. Import Movies

**For Symfony AI (PostgreSQL):**
```bash
# Quick test (1000 movies)
php bin/console app:import-movies --reset --limit=1000 --batch-size=50

# Full dataset (31,944 movies - ~40 minutes)
php bin/console app:import-movies --reset --batch-size=50
```

**For Typesense:**
```bash
# Import and auto-generate embeddings
php bin/console app:typesense-index --reset

# Typesense will generate embeddings via Ollama automatically
```

### 3. Start Symfony Server

```bash
symfony server:start
```

### 4. Access Interfaces

- **Symfony AI Interface**: http://localhost:8000
- **Typesense Interface**: http://localhost:8000/typesense
- **API Endpoints**:
  - Symfony AI: `GET /api/search?q=query`
  - Typesense: `GET /api/typesense/search?q=query`

## Search Examples

### Semantic Search (Concept Understanding)

Find Shrek without knowing the title:

```bash
# Symfony AI
curl "http://localhost:8000/api/search?q=green+ogre+living+in+swamp&limit=5"

# Typesense
curl "http://localhost:8000/api/typesense/search?q=green+ogre+living+in+swamp&limit=5"
```

**Both return:** Shrek as the top result, demonstrating semantic understanding.

### Keyword Search

```bash
# Symfony AI
curl "http://localhost:8000/api/search?q=fairy+tale&limit=5"

# Typesense
curl "http://localhost:8000/api/typesense/search?q=fairy+tale&limit=5"
```

**Results:**
- Pan's Labyrinth (has "fairy tale" 2x in keywords)
- Shrek 2 (has "fairy" 3x including "Fairy Godmother")
- Edward Scissorhands, Hook, Shrek...

### Fuzzy Matching (Typo Tolerance)

```bash
# Symfony AI
curl "http://localhost:8000/api/search?q=Batmn&limit=3"

# Typesense
curl "http://localhost:8000/api/typesense/search?q=Batmn&limit=3"
```

**Both find:** "Batman" despite the typo.

### Actor/Character Search

```bash
# Search for Eddie Murphy movies
curl "http://localhost:8000/api/search?q=Eddie+Murphy&limit=5"
```

**Results:** Beverly Hills Cop, 48 Hrs., Trading Places, Dreamgirls, Shrek

## Configuration

### Symfony AI (config/packages/symfony_ai.yaml)

```yaml
ai:
    store:
        postgres:
            hybrid:
                dsn: 'pgsql:host=postgres;dbname=hybrid_search'
                semantic_ratio: 0.3        # 30% semantic, 70% full-text
                text_search_strategy: 'bm25'
                rrf_k: 10
                normalize_scores: true
                fuzzy_enabled: true
                fuzzy_threshold: 0.3
```

**Key Parameters:**
- `semantic_ratio`: Balance between vector (0.0) and text (1.0)
- `text_search_strategy`: 'bm25' or 'ts_rank'
- `rrf_k`: RRF constant for rank fusion
- `fuzzy_threshold`: Trigram similarity (0.0-1.0)

### Typesense (config/packages/acseo_typesense.yaml)

```yaml
acseo_typesense:
    typesense:
        url: '%env(TYPESENSE_URL)%'
        key: '%env(TYPESENSE_KEY)%'
    collections:
        movies:
            fields:
                - name: embedding
                  type: 'float[]'
                  embed:
                      from: [title, overview]
                      model_config:
                          model_name: 'openai/nomic-embed-text'
                          url: 'http://ollama_embeddings:11434'
```

**Key Features:**
- Auto-embedding from Ollama
- Infix search enabled for partial matches
- Faceted search on genres and release_date

## Performance Comparison

### Import Speed (31,944 movies)

| Solution | Time | Speed |
|----------|------|-------|
| **Symfony AI** | ~40 min | ~13 movies/sec |
| **Typesense** | ~45 min | ~12 movies/sec |

*Both use Ollama with 4 parallel workers*

### Search Speed (Average)

| Query Type | Symfony AI | Typesense |
|------------|-----------|-----------|
| Simple keyword | 50-100ms | 30-80ms |
| Semantic (vector) | 80-150ms | 50-120ms |
| Hybrid (RRF) | 100-200ms | 60-150ms |

*Results may vary based on hardware and dataset size*

### Resource Usage

| Resource | Symfony AI | Typesense |
|----------|-----------|-----------|
| RAM (idle) | ~200MB (PostgreSQL) | ~500MB (Typesense) |
| RAM (indexed) | ~1.5GB | ~2GB |
| Disk space | ~8GB | ~6GB |

## Pros and Cons

### Symfony AI HybridStore

**Pros:**
- Full control over ranking algorithm
- No vendor lock-in (standard PostgreSQL)
- Complex SQL queries possible
- Integrated with existing PostgreSQL
- Configurable RRF weights
- No additional infrastructure cost

**Cons:**
- More complex setup (extensions, indexes)
- Manual tuning required
- Slower initial setup
- Limited horizontal scaling

### Typesense

**Pros:**
- Easier setup and configuration
- Built-in features (facets, geo-search)
- RESTful API (any language)
- Better horizontal scaling
- Auto-tuned hybrid search
- Great documentation

**Cons:**
- Additional service to manage
- Less control over algorithms
- Paid cloud option for scaling
- Not standard SQL
- Requires separate infrastructure

## Use Cases

### Choose Symfony AI HybridStore if:
- You already use PostgreSQL
- You need complex SQL queries
- You want full control over ranking
- You're building a custom solution
- Budget is tight (no additional services)
- You have PostgreSQL expertise

### Choose Typesense if:
- You want quick setup
- You need a managed solution
- You prefer API-based approach
- You need horizontal scaling
- You want built-in features (facets, etc.)
- You have microservices architecture

## API Comparison

### Search Request

**Symfony AI:**
```bash
GET /api/search?q=matrix&limit=10
```

**Typesense:**
```bash
GET /api/typesense/search?q=matrix&limit=10
```

### Response Format

Both return:
```json
{
  "query": "matrix",
  "hits": 10,
  "processingTimeMs": 120,
  "results": [
    {
      "id": 603,
      "title": "The Matrix",
      "overview": "...",
      "score": 85.5
    }
  ]
}
```

## Commands

```bash
# Symfony AI (PostgreSQL)
php bin/console app:import-movies --reset --limit=1000
php bin/console app:import-movies --reset  # Full import

# Typesense
php bin/console app:typesense-index --reset

# Database access
docker exec -it postgres_hybrid_search psql -U postgres -d hybrid_search

# Typesense API
curl "http://localhost:8108/collections/movies/documents/search?q=matrix&query_by=title,overview"

# Service logs
docker logs -f postgres_hybrid_search
docker logs -f typesense_search
docker logs -f ollama_embeddings
```

## Troubleshooting

### PostgreSQL Issues
```bash
# Check if pgvector is installed
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "SELECT * FROM pg_extension WHERE extname = 'vector';"

# Recreate extensions
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "
  CREATE EXTENSION IF NOT EXISTS vector;
  CREATE EXTENSION IF NOT EXISTS pg_trgm;
"
```

### Typesense Issues
```bash
# Check health
curl http://localhost:8108/health

# View collections
curl -H "X-TYPESENSE-API-KEY: 123" http://localhost:8108/collections

# Delete collection
curl -X DELETE -H "X-TYPESENSE-API-KEY: 123" http://localhost:8108/collections/movies
```

### Ollama Issues
```bash
# Check model
docker exec ollama_embeddings ollama list

# Re-download model
docker exec ollama_embeddings ollama pull nomic-embed-text

# Test embedding
curl http://localhost:11434/api/embeddings -d '{
  "model": "nomic-embed-text",
  "prompt": "test"
}'
```

## Documentation

### Symfony AI
- [Symfony AI Documentation](https://github.com/symfony/ai)
- [pgvector](https://github.com/pgvector/pgvector)
- [RRF Algorithm Paper](https://plg.uwaterloo.ca/~gvcormac/cormacksigir09-rrf.pdf)

### Typesense
- [Typesense Documentation](https://typesense.org/docs/)
- [Vector Search Guide](https://typesense.org/docs/guide/vector-search.html)
- [Hybrid Search](https://typesense.org/docs/guide/semantic-search.html)

### General
- [Ollama](https://ollama.ai/)
- [nomic-embed-text](https://huggingface.co/nomic-ai/nomic-embed-text-v1)

## Dataset

**Source:** 31,944 movies from TMDb
**Fields:**
- title, overview, genres
- release_date, poster
- TMDb metadata (keywords, cast, director)

**Enrichments:**
- Vector embeddings (768 dimensions)
- Full-text indexes
- Trigram indexes for fuzzy search

## License

MIT

## Credits

- **Symfony AI** - [symfony/ai](https://github.com/symfony/ai)
- **Typesense** - [typesense.org](https://typesense.org/)
- **Dataset** - TMDb (The Movie Database)
- **Embeddings** - [Ollama](https://ollama.ai/) with nomic-embed-text
- **PostgreSQL Extensions** - [pgvector](https://github.com/pgvector/pgvector), pg_trgm
