---
layout: default
title: Architecture
nav_order: 3
description: "Deep dive into the architecture of both hybrid search solutions"
---

# Architecture
{: .no_toc }

Understanding how both solutions implement hybrid search under the hood.
{: .fs-6 .fw-300 }

## Table of contents
{: .no_toc .text-delta }

1. TOC
{:toc}

---

## System Overview

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
     │                                          │
     ▼                                          ▼
PostgreSQL                                 Typesense
(31,944 movies)                           (31,944 movies)
```

## Data Flow

### Indexing Flow (Import)

#### Symfony AI (PostgreSQL)

```
1. TMDb Dataset
   │
   ├──> App\Command\ImportMoviesCommand
   │    │
   │    ├──> Batch Processing (50 movies/batch)
   │    │    │
   │    │    ├──> Ollama API (generate embeddings)
   │    │    │    └──> nomic-embed-text model
   │    │    │         └──> 768-dimensional vector
   │    │    │
   │    │    └──> Doctrine ORM
   │    │         └──> PostgreSQL INSERT
   │    │              ├──> movies table (text data)
   │    │              └──> embeddings (vector column)
   │    │
   │    └──> Post-processing
   │         ├──> ts_vector index (full-text)
   │         └──> pg_trgm index (fuzzy)
```

#### Typesense

```
1. TMDb Dataset
   │
   ├──> App\Command\TypesenseIndexCommand
   │    │
   │    ├──> HTTP Client → Typesense API
   │    │    │
   │    │    ├──> Document insertion (JSON)
   │    │    │    └──> title, overview, genres, etc.
   │    │    │
   │    │    └──> Auto-embedding configuration
   │    │         └──> Typesense calls Ollama
   │    │              └──> nomic-embed-text model
   │    │                   └──> 768-dimensional vector
   │    │
   │    └──> Automatic indexing
   │         ├──> Full-text index
   │         ├──> Vector index (HNSW)
   │         └──> Trigram index (typo tolerance)
```

### Search Flow (Query)

#### Symfony AI (PostgreSQL)

```
1. User Query: "green ogre swamp"
   │
   ├──> SearchController
   │    │
   │    ├──> Symfony AI HybridStore
   │    │    │
   │    │    ├──> Generate query embedding
   │    │    │    └──> Ollama (nomic-embed-text)
   │    │    │         └──> [0.123, -0.456, ...]
   │    │    │
   │    │    ├──> Execute 3 parallel queries:
   │    │    │    │
   │    │    │    ├──> Vector Search (semantic)
   │    │    │    │    SELECT *, embedding <=> $1 AS distance
   │    │    │    │    FROM movies
   │    │    │    │    ORDER BY distance
   │    │    │    │    └──> Results with ranks [1,2,3...]
   │    │    │    │
   │    │    │    ├──> Full-Text Search (keywords)
   │    │    │    │    SELECT *, ts_rank(search_vector, query) AS rank
   │    │    │    │    FROM movies
   │    │    │    │    WHERE search_vector @@ query
   │    │    │    │    └──> Results with ranks [1,2,3...]
   │    │    │    │
   │    │    │    └──> Fuzzy Search (typos)
   │    │    │         SELECT *, similarity(title, $1) AS sim
   │    │    │         FROM movies
   │    │    │         WHERE title % $1
   │    │    │         └──> Results with ranks [1,2,3...]
   │    │    │
   │    │    └──> RRF Algorithm (merge results)
   │    │         score = Σ (1 / (k + rank))
   │    │         └──> Final ranked results
   │    │
   │    └──> JSON Response
```

**Key PostgreSQL Functions:**

- `<=>` : Cosine distance for vector similarity
- `ts_rank()` : Text search relevance scoring
- `similarity()` : Trigram similarity (fuzzy matching)
- `@@` : Text search match operator
- `%` : Trigram similarity operator

#### Typesense

```
1. User Query: "green ogre swamp"
   │
   ├──> TypesenseController
   │    │
   │    ├──> HTTP Request → Typesense API
   │    │    │
   │    │    └──> POST /collections/movies/documents/search
   │    │         {
   │    │           "q": "green ogre swamp",
   │    │           "query_by": "title,overview,embedding",
   │    │           "vector_query": "embedding:([...], k:100)"
   │    │         }
   │    │
   │    ├──> Typesense Internal Processing:
   │    │    │
   │    │    ├──> Generate query embedding
   │    │    │    └──> Call Ollama via configured endpoint
   │    │    │         └──> [0.123, -0.456, ...]
   │    │    │
   │    │    ├──> Execute hybrid search:
   │    │    │    │
   │    │    │    ├──> Vector search (HNSW index)
   │    │    │    ├──> Full-text search (inverted index)
   │    │    │    └──> Typo tolerance (auto-applied)
   │    │    │
   │    │    └──> Built-in ranking algorithm
   │    │         └──> Weighted score (text + vector)
   │    │
   │    └──> JSON Response
```

## Database Schema

### PostgreSQL (Symfony AI)

```sql
CREATE TABLE movies (
    id SERIAL PRIMARY KEY,
    tmdb_id INTEGER UNIQUE NOT NULL,
    title VARCHAR(500) NOT NULL,
    overview TEXT,
    release_date DATE,
    poster_path VARCHAR(255),
    genres TEXT[],
    vote_average DECIMAL(3,1),
    vote_count INTEGER,

    -- Vector embedding (pgvector)
    embedding vector(768),

    -- Full-text search
    search_vector tsvector,

    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW()
);

-- Indexes
CREATE INDEX idx_movies_embedding ON movies
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);

CREATE INDEX idx_movies_search_vector ON movies
USING gin(search_vector);

CREATE INDEX idx_movies_title_trgm ON movies
USING gin(title gin_trgm_ops);

CREATE INDEX idx_movies_release_date ON movies(release_date);

-- Trigger to auto-update search_vector
CREATE TRIGGER tsvector_update BEFORE INSERT OR UPDATE
ON movies FOR EACH ROW EXECUTE FUNCTION
tsvector_update_trigger(search_vector, 'pg_catalog.english', title, overview);
```

**Index Types:**

- **IVFFlat (vector)**: Inverted File Flat for approximate nearest neighbor search
  - Faster than exact search, 95%+ accuracy
  - `lists = 100`: Number of clusters (tune based on dataset size)

- **GIN (search_vector)**: Generalized Inverted Index for full-text search
  - Optimized for text search operations
  - Supports `@@` operator

- **GIN (title_trgm)**: Trigram index for fuzzy matching
  - Supports `%` (similarity) operator
  - Enables typo tolerance

### Typesense Schema

```json
{
  "name": "movies",
  "fields": [
    {
      "name": "id",
      "type": "int32",
      "facet": false
    },
    {
      "name": "tmdb_id",
      "type": "int32",
      "facet": false
    },
    {
      "name": "title",
      "type": "string",
      "infix": true
    },
    {
      "name": "overview",
      "type": "string"
    },
    {
      "name": "genres",
      "type": "string[]",
      "facet": true
    },
    {
      "name": "release_date",
      "type": "string",
      "facet": true
    },
    {
      "name": "embedding",
      "type": "float[]",
      "embed": {
        "from": ["title", "overview"],
        "model_config": {
          "model_name": "openai/nomic-embed-text",
          "url": "http://ollama_embeddings:11434/api/embeddings",
          "api_key": "dummy"
        }
      },
      "num_dim": 768
    }
  ]
}
```

**Field Types:**

- **infix: true**: Enables partial string matching (e.g., "bat" matches "Batman")
- **facet: true**: Allows filtering and aggregation
- **embed**: Auto-generates embeddings via Ollama
- **float[]**: Vector field for semantic search

## RRF Algorithm (Reciprocal Rank Fusion)

### Symfony AI Implementation

The custom RRF implementation in Symfony AI HybridStore:

```php
// Simplified version
function rrf_score($results, $k = 60) {
    $scores = [];

    foreach ($results as $type => $ranked_results) {
        foreach ($ranked_results as $rank => $doc) {
            $id = $doc['id'];
            $rrf_score = 1 / ($k + $rank + 1);

            if (!isset($scores[$id])) {
                $scores[$id] = 0;
            }
            $scores[$id] += $rrf_score;
        }
    }

    arsort($scores);
    return $scores;
}
```

**Example:**

Query: "green ogre swamp"

```
Vector Results:      FTS Results:         Fuzzy Results:
1. Shrek (rank=1)    1. Shrek 2 (rank=1)  1. Shrek (rank=1)
2. Shrek 2 (rank=2)  2. Shrek (rank=2)    2. Green Zone (rank=2)
3. Green Mile (rank=3) 3. Swamp Thing (rank=3)

RRF Scores (k=60):
Shrek:       1/(60+1) + 1/(60+2) + 1/(60+1) = 0.0164 + 0.0161 + 0.0164 = 0.0489
Shrek 2:     1/(60+2) + 1/(60+1) = 0.0161 + 0.0164 = 0.0325
Green Mile:  1/(60+3) = 0.0159
Swamp Thing: 1/(60+3) = 0.0159

Final Ranking:
1. Shrek (0.0489)
2. Shrek 2 (0.0325)
3. Green Mile (0.0159)
4. Swamp Thing (0.0159)
```

### Typesense Implementation

Typesense uses a built-in weighted hybrid search:

```
score = (text_match_score × text_weight) + (vector_similarity × vector_weight)

Default weights (auto-tuned):
- text_weight: 0.7
- vector_weight: 0.3
```

You can configure weights in the search query:

```bash
curl -X POST http://localhost:8108/collections/movies/documents/search \
  -d '{
    "q": "green ogre swamp",
    "query_by": "title,overview",
    "vector_query": "embedding:([...], weight:0.3)"
  }'
```

## Embedding Generation

### Ollama Configuration

Both solutions use the same embedding model for fair comparison:

**Model**: nomic-embed-text
- **Dimensions**: 768
- **Max tokens**: 2048
- **Context window**: 8192
- **Performance**: ~50 embeddings/sec (CPU), ~200 embeddings/sec (GPU)

### Embedding Process

```python
# Conceptual representation
def generate_embedding(text):
    # 1. Tokenization
    tokens = tokenize(text)

    # 2. Model inference
    embedding = model.encode(tokens)

    # 3. Normalization
    normalized = normalize(embedding)

    return normalized  # [0.123, -0.456, ..., 0.789]
```

### Why 768 Dimensions?

- **Balance**: Good trade-off between accuracy and performance
- **Standard**: Common size for transformer models (BERT, RoBERTa)
- **Storage**: 768 × 4 bytes = 3KB per movie (float32)
- **Speed**: Fast cosine similarity computation

## Search Strategies

### Vector Search (Semantic)

**How it works:**

1. Convert query to embedding: "green ogre" → [0.12, -0.34, ...]
2. Compute cosine similarity with all movie embeddings
3. Return top K most similar

**Cosine Similarity:**

```
similarity(A, B) = (A · B) / (||A|| × ||B||)

Range: -1 (opposite) to 1 (identical)
```

**PostgreSQL:**

```sql
SELECT *,
       1 - (embedding <=> $query_embedding) AS similarity
FROM movies
ORDER BY embedding <=> $query_embedding
LIMIT 10;
```

**Typesense:**

```json
{
  "vector_query": "embedding:([0.12, -0.34, ...], k:10)"
}
```

### Full-Text Search (Keywords)

**How it works:**

1. Tokenize query: "fairy tale" → ["fairy", "tale"]
2. Match against inverted index
3. Rank by relevance (BM25 or TF-IDF)

**BM25 Ranking:**

```
BM25(D,Q) = Σ IDF(qi) × (f(qi,D) × (k1 + 1)) /
                        (f(qi,D) + k1 × (1 - b + b × |D|/avgdl))

where:
- IDF: Inverse document frequency
- f(qi,D): Term frequency in document
- k1, b: Tuning parameters
- |D|: Document length
- avgdl: Average document length
```

**PostgreSQL:**

```sql
SELECT *,
       ts_rank(search_vector, to_tsquery('english', 'fairy & tale')) AS rank
FROM movies
WHERE search_vector @@ to_tsquery('english', 'fairy & tale')
ORDER BY rank DESC;
```

**Typesense:**

Uses BM25 by default with automatic tuning.

### Fuzzy Search (Typo Tolerance)

**How it works:**

1. Compute trigram similarity: "Batmn" vs "Batman"
2. Match if similarity > threshold (e.g., 0.3)
3. Rank by similarity score

**Trigram Similarity:**

```
Trigrams("Batman") = {"Bat", "atm", "tma", "man"}
Trigrams("Batmn")  = {"Bat", "atm", "tmn"}

Similarity = |intersection| / |union|
           = 2 / 5 = 0.4 > threshold (0.3) ✓
```

**PostgreSQL:**

```sql
SELECT *,
       similarity(title, 'Batmn') AS sim
FROM movies
WHERE title % 'Batmn'  -- % is similarity operator
ORDER BY sim DESC;
```

**Typesense:**

Built-in typo tolerance (configurable):

```json
{
  "q": "Batmn",
  "num_typos": 2  // Allow up to 2 typos
}
```

## Performance Optimizations

### PostgreSQL Optimizations

1. **IVFFlat Index**: Approximate nearest neighbor (ANN) search
   - 10-100× faster than exact search
   - 95%+ accuracy with proper tuning

2. **Parallel Workers**: Utilize multiple CPU cores
   ```sql
   SET max_parallel_workers_per_gather = 4;
   ```

3. **Shared Buffers**: Cache frequently accessed data
   ```sql
   SET shared_buffers = '2GB';
   ```

4. **Work Memory**: Speed up sorting and aggregation
   ```sql
   SET work_mem = '256MB';
   ```

### Typesense Optimizations

1. **HNSW Index**: Hierarchical Navigable Small World graph
   - O(log N) search complexity
   - Better accuracy than IVFFlat

2. **Memory-First**: Keep hot data in RAM
   - Faster access than disk-based systems
   - Automatic cache management

3. **Parallel Processing**: Auto-parallelization
   - Distributed across CPU cores
   - No configuration needed

## Scaling Considerations

### Horizontal Scaling

**Symfony AI (PostgreSQL):**
- ❌ Limited horizontal scaling
- ✅ Can use read replicas for search
- ✅ Sharding possible but complex

**Typesense:**
- ✅ Built-in clustering
- ✅ Automatic sharding
- ✅ Easy to add nodes

### Vertical Scaling

**Symfony AI (PostgreSQL):**
- ✅ Excellent vertical scaling
- ✅ Utilize more RAM/CPU easily
- ✅ Well-documented tuning

**Typesense:**
- ✅ Great vertical scaling
- ✅ Memory-first design benefits from RAM
- ✅ Multi-core friendly

## Next Steps

- [Symfony AI Configuration]({% link symfony-ai.md %}) - Detailed setup and tuning
- [Typesense Configuration]({% link typesense.md %}) - Detailed setup and tuning
- [Performance Comparison]({% link comparison.md %}) - Benchmarks and analysis
