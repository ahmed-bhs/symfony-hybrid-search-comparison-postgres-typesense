---
layout: default
title: Symfony AI Guide
nav_order: 4
description: "Configure and optimize Symfony AI HybridStore for PostgreSQL-based hybrid search"
---

# Symfony AI HybridStore Guide
{: .no_toc }

Complete guide to using and configuring Symfony AI's PostgreSQL-based hybrid search.
{: .fs-6 .fw-300 }

## Table of contents
{: .no_toc .text-delta }

1. TOC
{:toc}

---

## Overview

Symfony AI HybridStore is a custom hybrid search implementation that combines:
- **Vector search** (pgvector) for semantic understanding
- **Full-text search** (BM25) for keyword matching
- **Fuzzy matching** (pg_trgm) for typo tolerance
- **RRF algorithm** for merging results

{: .highlight }
> Best for: Projects already using PostgreSQL, need full SQL control, or want custom ranking algorithms.

## Architecture

```
User Query
    │
    ├──> Generate Embedding (Ollama)
    │    └──> [0.123, -0.456, ..., 0.789] (768 dims)
    │
    ├──> PostgreSQL Hybrid Search
    │    │
    │    ├──> Vector Search (pgvector)
    │    │    SELECT *, embedding <=> $1 AS distance
    │    │    ORDER BY distance LIMIT 100
    │    │    └──> Results: [1: Shrek, 2: Shrek 2, ...]
    │    │
    │    ├──> Full-Text Search (BM25)
    │    │    SELECT *, bm25_score(content, $1) AS score
    │    │    ORDER BY score DESC LIMIT 100
    │    │    └──> Results: [1: Pan's Labyrinth, 2: Shrek, ...]
    │    │
    │    └──> Fuzzy Search (pg_trgm)
    │         SELECT *, similarity(title, $1) AS sim
    │         WHERE title % $1
    │         └──> Results: [1: Shrek, 2: Green Zone, ...]
    │
    └──> RRF Merge
         score(doc) = Σ (1 / (k + rank))
         └──> Final: [1: Shrek, 2: Shrek 2, 3: Pan's Labyrinth]
```

## Installation

### Prerequisites

```bash
# PostgreSQL 16 with extensions
docker run -d --name postgres_hybrid_search \
  -e POSTGRES_PASSWORD=postgres \
  -p 5432:5432 \
  ankane/pgvector:latest

# Install extensions
docker exec postgres_hybrid_search psql -U postgres -c "
  CREATE DATABASE hybrid_search;
  \c hybrid_search
  CREATE EXTENSION IF NOT EXISTS vector;
  CREATE EXTENSION IF NOT EXISTS pg_trgm;
"
```

### Install Symfony AI

```bash
composer require symfony/ai-bundle
composer require symfony/ai-store
composer require symfony/ai-platform
```

## Configuration

### Basic Configuration

Create `config/packages/symfony_ai.yaml`:

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

                # Enable hybrid search
                hybrid:
                    enabled: true
                    content_field: 'content'
                    semantic_ratio: 0.3
                    text_search_strategy: 'bm25'
                    rrf_k: 10
                    normalize_scores: true

    vectorizer:
        ollama:
            model: 'nomic-embed-text'
```

### Configuration Reference

#### Store Configuration

| Parameter | Type | Default | Description |
|:----------|:-----|:--------|:------------|
| `dsn` | string | required | PostgreSQL connection string |
| `username` | string | required | Database username |
| `password` | string | required | Database password |
| `table_name` | string | required | Table containing documents |
| `vector_field` | string | `embedding` | Column storing vectors |
| `distance` | string | `cosine` | Distance metric: `cosine`, `l2`, `inner_product` |

#### Hybrid Search Configuration

| Parameter | Type | Default | Description |
|:----------|:-----|:--------|:------------|
| `enabled` | bool | `false` | Enable hybrid search mode |
| `content_field` | string | `content` | Field containing searchable text |
| `semantic_ratio` | float | `0.5` | Balance: 0.0 (text) to 1.0 (semantic) |
| `language` | string | `english` | Stemming language for FTS |
| `text_search_strategy` | string | `bm25` | `bm25` or `ts_rank` |
| `rrf_k` | int | `60` | RRF constant (lower = stronger fusion) |
| `normalize_scores` | bool | `false` | Scale scores to 0-100 |
| `default_min_score` | float | `0` | Minimum score threshold |

#### Fuzzy Search Configuration

| Parameter | Type | Default | Description |
|:----------|:-----|:--------|:------------|
| `fuzzy_primary_threshold` | float | `0.3` | Primary similarity threshold |
| `fuzzy_secondary_threshold` | float | `0.2` | Secondary threshold for more typos |
| `fuzzy_strict_threshold` | float | `0.15` | Strict threshold (very fuzzy) |
| `fuzzy_weight` | float | `0.3` | Weight of fuzzy results in RRF |

#### Searchable Attributes

| Parameter | Type | Default | Description |
|:----------|:-----|:--------|:------------|
| `searchable_attributes` | array | `[]` | Fields to search (empty = all content) |

**Example:**
```yaml
searchable_attributes: ['title', 'overview']  # Search only title and overview
```

## Database Schema

### Movies Table

```sql
CREATE TABLE movies (
    id SERIAL PRIMARY KEY,
    tmdb_id INTEGER UNIQUE NOT NULL,
    title VARCHAR(500) NOT NULL,
    overview TEXT,
    release_date DATE,
    poster_path VARCHAR(255),
    genres TEXT[],

    -- Combined searchable content
    content TEXT,

    -- Vector embedding
    embedding vector(768),

    -- Metadata
    vote_average DECIMAL(3,1),
    vote_count INTEGER,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Required Indexes

```sql
-- Vector search (IVFFlat for speed)
CREATE INDEX idx_movies_embedding ON movies
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);

-- Fuzzy search (trigrams)
CREATE INDEX idx_movies_title_trgm ON movies
USING gin(title gin_trgm_ops);

CREATE INDEX idx_movies_content_trgm ON movies
USING gin(content gin_trgm_ops);

-- Optional: traditional FTS (if not using BM25)
CREATE INDEX idx_movies_content_fts ON movies
USING gin(to_tsvector('english', content));
```

### Index Tuning

**IVFFlat lists parameter:**

| Dataset Size | Recommended Lists |
|:-------------|:------------------|
| < 1,000 | 10-20 |
| 1,000 - 10,000 | 50-100 |
| 10,000 - 100,000 | 100-500 |
| > 100,000 | 500-1000 |

```sql
-- Rebuild index with new lists value
DROP INDEX idx_movies_embedding;
CREATE INDEX idx_movies_embedding ON movies
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 200);
```

## Usage

### Import Data

```php
use Symfony\Component\AI\Store\PostgresStore;
use Symfony\Component\AI\Vectorizer\OllamaVectorizer;

class ImportMoviesCommand extends Command
{
    public function __construct(
        private PostgresStore $store,
        private OllamaVectorizer $vectorizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $movies = $this->loadMovies(); // Your data source

        foreach ($movies as $movie) {
            // Prepare content for embedding
            $content = sprintf(
                "%s %s %s",
                $movie['title'],
                $movie['overview'],
                implode(' ', $movie['genres'])
            );

            // Generate embedding
            $embedding = $this->vectorizer->embed($content);

            // Insert into store
            $this->store->insert([
                'tmdb_id' => $movie['id'],
                'title' => $movie['title'],
                'overview' => $movie['overview'],
                'content' => $content,
                'embedding' => $embedding,
                'genres' => $movie['genres'],
                'release_date' => $movie['release_date'],
            ]);
        }

        return Command::SUCCESS;
    }
}
```

### Search Data

```php
use Symfony\Component\AI\Store\PostgresStore;

class SearchController extends AbstractController
{
    public function __construct(
        private PostgresStore $store,
    ) {}

    #[Route('/api/search', name: 'search')]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = $request->query->getInt('limit', 10);

        // Hybrid search
        $results = $this->store->search(
            query: $query,
            limit: $limit,
            options: [
                'min_score' => 0,
            ]
        );

        return $this->json([
            'query' => $query,
            'hits' => count($results),
            'results' => $results,
        ]);
    }
}
```

## Tuning Parameters

### Semantic Ratio

Controls the balance between semantic and keyword search:

```yaml
semantic_ratio: 0.3  # 30% semantic, 70% text
```

**When to adjust:**

| Use Case | Recommended Ratio | Why |
|:---------|:------------------|:----|
| Conceptual search | 0.7 - 0.9 | Prioritize semantic understanding |
| Exact match needed | 0.1 - 0.3 | Prioritize keywords |
| Balanced | 0.4 - 0.6 | Mix of both |
| Movie database | 0.3 - 0.4 | Keywords important (genres, actors) |

**Examples:**

```bash
# High semantic ratio (0.8): Good for concept search
"green ogre in swamp" → Shrek (great semantic match)

# Low semantic ratio (0.2): Good for keyword search
"Eddie Murphy" → Beverly Hills Cop, Shrek (exact name match)
```

### RRF K Parameter

Controls how ranks are fused:

```yaml
rrf_k: 10  # Lower = stronger fusion
```

**Formula:**
```
score(doc) = Σ (1 / (k + rank))
```

**When to adjust:**

| K Value | Effect | Use Case |
|:--------|:-------|:---------|
| k = 10 | Strong fusion | Amplify top results |
| k = 60 | Balanced | Default recommendation |
| k = 100 | Weak fusion | Preserve original rankings |

**Example:**

```
Document ranked #1 in vector, #10 in text:

k=10:  1/(10+1) + 1/(10+10) = 0.091 + 0.050 = 0.141
k=60:  1/(60+1) + 1/(60+10) = 0.016 + 0.014 = 0.030
k=100: 1/(100+1) + 1/(100+10) = 0.010 + 0.009 = 0.019

Lower k gives more weight to top ranks!
```

### Fuzzy Thresholds

Control typo tolerance:

```yaml
fuzzy_primary_threshold: 0.3    # Strict matching
fuzzy_secondary_threshold: 0.2  # More lenient
fuzzy_strict_threshold: 0.15    # Very lenient
```

**Threshold guide:**

| Threshold | Match Quality | Example |
|:----------|:--------------|:--------|
| 0.5+ | Very strict | "Batman" ≈ "Batma" |
| 0.3-0.5 | Strict | "Batman" ≈ "Batmn" |
| 0.2-0.3 | Lenient | "Batman" ≈ "Btmn" |
| < 0.2 | Very lenient | "Batman" ≈ "Btm" |

### Text Search Strategy

Choose between BM25 and PostgreSQL FTS:

```yaml
text_search_strategy: 'bm25'  # or 'ts_rank'
```

**Comparison:**

| Feature | BM25 | ts_rank |
|:--------|:-----|:--------|
| **Algorithm** | Okapi BM25 | TF-IDF variant |
| **Accuracy** | Better for long documents | Good for short text |
| **Speed** | Slightly slower | Faster |
| **Tuning** | More parameters | Fewer parameters |
| **Best for** | Movie overviews, descriptions | Titles, short fields |

## Advanced Usage

### Custom Filters

```php
$results = $this->store->search(
    query: 'action movie',
    limit: 10,
    options: [
        'filters' => [
            'release_date' => ['>', '2020-01-01'],
            'vote_average' => ['>=', 7.0],
        ],
    ]
);
```

### Boosting Specific Fields

```yaml
# Search only in specific fields
searchable_attributes: ['title^2', 'overview^1']  # Title weighted 2x
```

### Explain Scores

```php
$results = $this->store->search(
    query: 'matrix',
    limit: 10,
    options: [
        'explain' => true,  // Include score breakdown
    ]
);

// Result includes:
// - vector_score
// - text_score
// - fuzzy_score
// - final_rrf_score
```

## Performance Optimization

### PostgreSQL Tuning

```sql
-- Increase shared buffers
ALTER SYSTEM SET shared_buffers = '2GB';

-- Increase work memory
ALTER SYSTEM SET work_mem = '256MB';

-- Enable parallel workers
ALTER SYSTEM SET max_parallel_workers_per_gather = 4;

-- Restart PostgreSQL
pg_ctl restart
```

### Query Optimization

```sql
-- Analyze tables regularly
ANALYZE movies;

-- Vacuum to reclaim space
VACUUM ANALYZE movies;

-- Check index usage
SELECT schemaname, tablename, indexname, idx_scan
FROM pg_stat_user_indexes
WHERE tablename = 'movies';
```

### Monitoring

```sql
-- Check slow queries
SELECT query, mean_exec_time, calls
FROM pg_stat_statements
WHERE query LIKE '%movies%'
ORDER BY mean_exec_time DESC
LIMIT 10;

-- Check index size
SELECT pg_size_pretty(pg_relation_size('idx_movies_embedding'));
```

## Troubleshooting

### Issue: Slow vector search

**Solutions:**

1. Rebuild IVFFlat index with more lists:
```sql
DROP INDEX idx_movies_embedding;
CREATE INDEX idx_movies_embedding ON movies
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 200);
```

2. Increase PostgreSQL resources:
```sql
ALTER SYSTEM SET shared_buffers = '4GB';
ALTER SYSTEM SET effective_cache_size = '8GB';
```

3. Use exact search for small datasets (< 1000 docs):
```sql
-- No index needed, exact search is fast enough
SELECT * FROM movies
ORDER BY embedding <=> $1
LIMIT 10;
```

### Issue: Poor fuzzy matching

**Solutions:**

1. Lower fuzzy threshold:
```yaml
fuzzy_primary_threshold: 0.2  # More lenient
```

2. Add more trigram indexes:
```sql
CREATE INDEX idx_movies_overview_trgm ON movies
USING gin(overview gin_trgm_ops);
```

### Issue: Text search not finding results

**Solutions:**

1. Check stemming language:
```yaml
language: 'english'  # or 'french', 'german', etc.
```

2. Rebuild content field:
```sql
UPDATE movies
SET content = title || ' ' || overview || ' ' || array_to_string(genres, ' ');
```

3. Use BM25 instead of ts_rank:
```yaml
text_search_strategy: 'bm25'
```

### Issue: RRF scores too low

**Solutions:**

1. Enable score normalization:
```yaml
normalize_scores: true
```

2. Adjust RRF k parameter:
```yaml
rrf_k: 10  # Lower k = higher scores
```

## Example Queries

### Semantic Search

```bash
curl "http://localhost:8000/api/search?q=green+ogre+swamp"
```

### Keyword Search

```bash
curl "http://localhost:8000/api/search?q=Eddie+Murphy"
```

### Fuzzy Search

```bash
curl "http://localhost:8000/api/search?q=Batmn"
```

### Filtered Search

```bash
curl "http://localhost:8000/api/search?q=action&min_score=50&limit=5"
```

## Best Practices

1. **Index after bulk imports**: Create indexes after importing data, not before
2. **Normalize embeddings**: Always normalize vectors before storage
3. **Use connection pooling**: Configure pgBouncer for high-traffic apps
4. **Monitor index usage**: Regularly check `pg_stat_user_indexes`
5. **Vacuum regularly**: Run `VACUUM ANALYZE` weekly
6. **Tune for your data**: Adjust semantic_ratio and rrf_k based on search patterns
7. **Use read replicas**: Offload search queries to replicas

## Next Steps

- [Typesense Guide]({% link typesense.md %}) - Compare with Typesense
- [Performance Comparison]({% link comparison.md %}) - Benchmarks
- [Architecture]({% link architecture.md %}) - Deep dive
