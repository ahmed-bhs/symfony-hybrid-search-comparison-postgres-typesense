---
layout: default
title: Typesense Guide
nav_order: 5
description: "Configure and optimize Typesense for lightning-fast hybrid search"
---

# Typesense Guide
{: .no_toc }

Complete guide to using and configuring Typesense for hybrid search.
{: .fs-6 .fw-300 }

## Table of contents
{: .no_toc .text-delta }

1. TOC
{:toc}

---

## Overview

Typesense is a fast, typo-tolerant search engine with built-in hybrid search capabilities:
- **Vector search** (HNSW) for semantic understanding
- **Full-text search** (BM25-like) for keyword matching
- **Typo tolerance** (built-in) for fuzzy matching
- **Auto-embeddings** via Ollama integration

{: .highlight }
> Best for: Quick setup, managed solution, microservices, or API-first approach.

## Architecture

```
User Query
    │
    ├──> Typesense HTTP API
    │    │
    │    ├──> Generate Embedding (via Ollama)
    │    │    POST http://ollama:11434/api/embeddings
    │    │    └──> [0.123, -0.456, ..., 0.789] (768 dims)
    │    │
    │    ├──> Hybrid Search Engine
    │    │    │
    │    │    ├──> Vector Search (HNSW index)
    │    │    │    └──> Approximate nearest neighbor
    │    │    │         └──> Results with scores
    │    │    │
    │    │    ├──> Full-Text Search (BM25-like)
    │    │    │    └──> Inverted index lookup
    │    │    │         └──> Results with scores
    │    │    │
    │    │    └──> Typo Tolerance (N-grams)
    │    │         └──> Fuzzy matching
    │    │              └──> Results with scores
    │    │
    │    └──> Weighted Scoring
    │         score = (text × 0.7) + (vector × 0.3)
    │         └──> Final ranked results
    │
    └──> JSON Response
```

## Installation

### Using Docker

```bash
# Start Typesense server
docker run -d --name typesense_search \
  -p 8108:8108 \
  -v /tmp/typesense:/data \
  typesense/typesense:27.1 \
  --data-dir /data \
  --api-key=123 \
  --enable-cors
```

### Using Docker Compose

```yaml
version: '3.9'

services:
  typesense:
    image: typesense/typesense:27.1
    container_name: typesense_search
    ports:
      - "8108:8108"
    volumes:
      - ./typesense-data:/data
    command: >
      --data-dir /data
      --api-key=123
      --enable-cors
```

### Install Symfony Bundle

```bash
composer require acseo/typesense-bundle
```

## Configuration

### Environment Variables

Create `.env.local`:

```bash
TYPESENSE_URL=http://localhost:8108
TYPESENSE_KEY=123
```

### Bundle Configuration

Create `config/packages/acseo_typesense.yaml`:

```yaml
acseo_typesense:
    typesense:
        url: '%env(TYPESENSE_URL)%'
        key: '%env(TYPESENSE_KEY)%'

    collections:
        movies:
            entity: 'App\Entity\Movie'
            fields:
                - name: id
                  entity_attribute: id
                  type: string

                - name: tmdb_id
                  entity_attribute: tmdb_id
                  type: int32

                - name: title
                  entity_attribute: title
                  type: string
                  infix: true

                - name: overview
                  entity_attribute: overview
                  type: string
                  optional: true
                  infix: true

                - name: genres
                  entity_attribute: genres
                  type: 'string[]'
                  facet: true
                  optional: true

                - name: release_date
                  entity_attribute: release_date
                  type: int32
                  facet: true
                  optional: true

                - name: embedding
                  type: 'float[]'
                  optional: true
                  embed:
                      from: [title, overview]
                      model_config:
                          model_name: 'openai/nomic-embed-text'
                          api_key: 'dummy'
                          url: 'http://localhost:11434'

            default_sorting_field: tmdb_id
```

### Configuration Reference

#### Field Types

| Type | Description | Example |
|:-----|:------------|:--------|
| `string` | Text field | "The Matrix" |
| `int32` | Integer (32-bit) | 2001 |
| `float` | Floating point | 8.5 |
| `bool` | Boolean | true |
| `string[]` | Array of strings | ["Action", "Sci-Fi"] |
| `float[]` | Vector embedding | [0.1, -0.2, ...] |

#### Field Options

| Option | Type | Description |
|:-------|:-----|:------------|
| `facet` | bool | Enable faceting/filtering |
| `optional` | bool | Field can be null |
| `infix` | bool | Enable partial matching |
| `sort` | bool | Enable sorting |
| `index` | bool | Include in search index |

#### Embedding Configuration

```yaml
embed:
    from: [title, overview]         # Fields to embed
    model_config:
        model_name: 'openai/nomic-embed-text'
        api_key: 'dummy'            # Required but can be dummy for Ollama
        url: 'http://localhost:11434'
```

**Supported embedding providers:**
- Ollama (local)
- OpenAI
- Cohere
- PaLM
- GCP Vertex AI

## Usage

### Create Collection

```bash
# Via Symfony command
php bin/console typesense:create movies

# Or via API
curl -X POST http://localhost:8108/collections \
  -H "X-TYPESENSE-API-KEY: 123" \
  -d '{
    "name": "movies",
    "fields": [
      {"name": "title", "type": "string"},
      {"name": "overview", "type": "string"},
      {"name": "embedding", "type": "float[]", "num_dim": 768}
    ]
  }'
```

### Index Documents

#### Via Symfony Command

```php
use ACSEO\TypesenseBundle\Client\TypesenseClient;

class TypesenseIndexCommand extends Command
{
    public function __construct(
        private TypesenseClient $client,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $movies = $this->loadMovies(); // Your data source

        foreach ($movies as $movie) {
            $this->client->index('movies', [
                'id' => (string) $movie['id'],
                'tmdb_id' => $movie['tmdb_id'],
                'title' => $movie['title'],
                'overview' => $movie['overview'],
                'genres' => $movie['genres'],
                'release_date' => strtotime($movie['release_date']),
            ]);
            // Typesense auto-generates embedding via Ollama
        }

        return Command::SUCCESS;
    }
}
```

#### Via Direct API

```bash
curl -X POST http://localhost:8108/collections/movies/documents \
  -H "X-TYPESENSE-API-KEY: 123" \
  -d '{
    "id": "603",
    "title": "The Matrix",
    "overview": "Set in the 22nd century...",
    "genres": ["Action", "Sci-Fi"]
  }'
```

### Search Documents

#### Via Symfony Controller

```php
use ACSEO\TypesenseBundle\Client\TypesenseClient;

class TypesenseController extends AbstractController
{
    public function __construct(
        private TypesenseClient $client,
    ) {}

    #[Route('/api/typesense/search', name: 'typesense_search')]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = $request->query->getInt('limit', 10);

        $results = $this->client->search('movies', [
            'q' => $query,
            'query_by' => 'title,overview,embedding',
            'per_page' => $limit,
        ]);

        return $this->json([
            'query' => $query,
            'hits' => $results['found'],
            'results' => $results['hits'],
        ]);
    }
}
```

#### Via Direct API

```bash
curl "http://localhost:8108/collections/movies/documents/search?\
q=green+ogre+swamp&\
query_by=title,overview,embedding&\
per_page=10" \
  -H "X-TYPESENSE-API-KEY: 123"
```

## Advanced Search

### Hybrid Search

Combine text and vector search:

```bash
curl -X POST http://localhost:8108/multi_search \
  -H "X-TYPESENSE-API-KEY: 123" \
  -d '{
    "searches": [{
      "collection": "movies",
      "q": "green ogre swamp",
      "query_by": "title,overview",
      "vector_query": "embedding:([0.1, -0.2, ...], k:100)"
    }]
  }'
```

**Or use auto-embedding:**

```bash
curl "http://localhost:8108/collections/movies/documents/search?\
q=green+ogre+swamp&\
query_by=title,overview,embedding" \
  -H "X-TYPESENSE-API-KEY: 123"
```

Typesense automatically:
1. Generates embedding for query via Ollama
2. Searches both text and vector fields
3. Combines results with weighted scoring

### Filtering

```bash
# Movies after 2020 with rating > 7.0
curl "http://localhost:8108/collections/movies/documents/search?\
q=action&\
query_by=title,overview&\
filter_by=release_date:>1577836800 && vote_average:>7.0" \
  -H "X-TYPESENSE-API-KEY: 123"
```

**Filter operators:**
- `=` : Equals
- `!=` : Not equals
- `>` : Greater than
- `<` : Less than
- `>=` : Greater than or equal
- `<=` : Less than or equal
- `:` : In (for arrays)
- `&&` : AND
- `||` : OR

### Faceting

```bash
# Get genre distribution
curl "http://localhost:8108/collections/movies/documents/search?\
q=*&\
facet_by=genres,release_date" \
  -H "X-TYPESENSE-API-KEY: 123"
```

**Response:**
```json
{
  "facet_counts": [
    {
      "field_name": "genres",
      "counts": [
        {"value": "Action", "count": 5234},
        {"value": "Drama", "count": 4521},
        {"value": "Comedy", "count": 3892}
      ]
    }
  ]
}
```

### Typo Tolerance

```bash
# Automatic typo correction (default)
curl "http://localhost:8108/collections/movies/documents/search?\
q=Batmn&\
query_by=title" \
  -H "X-TYPESENSE-API-KEY: 123"

# Configure typo tolerance
curl "http://localhost:8108/collections/movies/documents/search?\
q=Batmn&\
query_by=title&\
num_typos=2&\
typo_tokens_threshold=1" \
  -H "X-TYPESENSE-API-KEY: 123"
```

**Typo parameters:**
- `num_typos`: Maximum typos allowed (0-2)
- `typo_tokens_threshold`: Minimum word length for typo tolerance

### Sorting

```bash
# Sort by release date
curl "http://localhost:8108/collections/movies/documents/search?\
q=action&\
query_by=title,overview&\
sort_by=release_date:desc,vote_average:desc" \
  -H "X-TYPESENSE-API-KEY: 123"
```

### Highlighting

```bash
# Highlight matched terms
curl "http://localhost:8108/collections/movies/documents/search?\
q=matrix&\
query_by=title,overview&\
highlight_fields=title,overview&\
highlight_full_fields=title,overview" \
  -H "X-TYPESENSE-API-KEY: 123"
```

**Response:**
```json
{
  "hits": [{
    "highlights": [
      {
        "field": "title",
        "snippet": "The <mark>Matrix</mark>"
      }
    ]
  }]
}
```

### Grouping

```bash
# Group by release year
curl "http://localhost:8108/collections/movies/documents/search?\
q=action&\
query_by=title,overview&\
group_by=release_date&\
group_limit=3" \
  -H "X-TYPESENSE-API-KEY: 123"
```

## Tuning Parameters

### Search Parameters

| Parameter | Default | Description |
|:----------|:--------|:------------|
| `per_page` | 10 | Results per page |
| `page` | 1 | Page number |
| `num_typos` | 2 | Max typos allowed |
| `prefix` | true | Enable prefix search |
| `infix` | off | Infix search mode |
| `max_candidates` | 4 | Max typo candidates |
| `exhaustive_search` | false | Force exact search |

### Vector Search Parameters

| Parameter | Description |
|:----------|:------------|
| `k` | Number of nearest neighbors (default: 10) |
| `distance_threshold` | Maximum distance for results |
| `alpha` | Weight for text vs vector (0.0-1.0) |

**Example:**

```bash
# 70% text, 30% vector
curl "http://localhost:8108/collections/movies/documents/search?\
q=matrix&\
query_by=title,overview,embedding&\
alpha=0.7" \
  -H "X-TYPESENSE-API-KEY: 123"
```

### Collection Parameters

```json
{
  "name": "movies",
  "fields": [...],
  "default_sorting_field": "tmdb_id",
  "token_separators": [".", "-", "_"],
  "symbols_to_index": ["@", "#"],
  "enable_nested_fields": false
}
```

## Performance Optimization

### Index Settings

```bash
# Create collection with optimized settings
curl -X POST http://localhost:8108/collections \
  -H "X-TYPESENSE-API-KEY: 123" \
  -d '{
    "name": "movies",
    "fields": [...],
    "enable_nested_fields": false,
    "token_separators": ["-", "_"],
    "symbols_to_index": []
  }'
```

**Tips:**
- Disable `enable_nested_fields` if not needed
- Minimize `symbols_to_index` for better performance
- Use specific `token_separators` for your data

### Memory Management

Typesense keeps indexes in RAM for speed:

**Memory estimation:**
```
RAM needed ≈ (num_documents × avg_doc_size × 2) + embedding_index_size

Example (31,944 movies):
- Documents: 31,944 × 2KB × 2 = 128MB
- Embeddings: 31,944 × 768 × 4 bytes × 1.5 = 147MB
- Total: ~300MB
```

### Batch Imports

For faster imports, use batch API:

```bash
# Import multiple documents at once
curl -X POST http://localhost:8108/collections/movies/documents/import \
  -H "X-TYPESENSE-API-KEY: 123" \
  -d '
{"id":"1","title":"Movie 1"}
{"id":"2","title":"Movie 2"}
{"id":"3","title":"Movie 3"}
'
```

**Via Symfony:**

```php
$batch = [];
foreach ($movies as $movie) {
    $batch[] = $movie;

    if (count($batch) >= 100) {
        $this->client->importDocuments('movies', $batch);
        $batch = [];
    }
}
```

### Caching

Typesense has built-in query caching:

```bash
# Enable caching (default)
curl "http://localhost:8108/collections/movies/documents/search?\
q=matrix&\
use_cache=true" \
  -H "X-TYPESENSE-API-KEY: 123"
```

## Monitoring

### Health Check

```bash
curl http://localhost:8108/health
```

### Collection Stats

```bash
curl http://localhost:8108/collections/movies \
  -H "X-TYPESENSE-API-KEY: 123"
```

**Response:**
```json
{
  "name": "movies",
  "num_documents": 31944,
  "num_memory_shards": 1,
  "created_at": 1640995200
}
```

### Server Metrics

```bash
curl http://localhost:8108/metrics.json \
  -H "X-TYPESENSE-API-KEY: 123"
```

## Troubleshooting

### Issue: Typesense won't start

**Solution:**

```bash
# Check logs
docker logs typesense_search

# Verify data directory permissions
chmod -R 755 /tmp/typesense

# Restart with clean state
docker rm -f typesense_search
docker run -d --name typesense_search \
  -p 8108:8108 \
  typesense/typesense:27.1 \
  --data-dir /data \
  --api-key=123
```

### Issue: Embeddings not generated

**Solution:**

1. Check Ollama connection:
```bash
curl http://localhost:11434/api/tags
```

2. Verify embed configuration:
```yaml
embed:
    from: [title, overview]
    model_config:
        model_name: 'openai/nomic-embed-text'
        url: 'http://localhost:11434'  # Must be accessible from Typesense
```

3. Use Docker network:
```yaml
services:
  typesense:
    networks:
      - app_network
  ollama:
    networks:
      - app_network
```

### Issue: Search returns no results

**Solutions:**

1. Check collection exists:
```bash
curl http://localhost:8108/collections \
  -H "X-TYPESENSE-API-KEY: 123"
```

2. Verify documents indexed:
```bash
curl http://localhost:8108/collections/movies \
  -H "X-TYPESENSE-API-KEY: 123"
```

3. Test simple query:
```bash
curl "http://localhost:8108/collections/movies/documents/search?q=*" \
  -H "X-TYPESENSE-API-KEY: 123"
```

### Issue: Slow searches

**Solutions:**

1. Reduce `k` for vector search:
```bash
vector_query="embedding:([...], k:50)"  # Instead of k:100
```

2. Disable typo tolerance for exact matches:
```bash
num_typos=0
```

3. Use smaller embedding dimensions:
```yaml
# In collection schema
num_dim: 384  # Instead of 768
```

4. Add more RAM:
```bash
docker run -d --name typesense_search \
  --memory=4g \
  ...
```

## Direct API Examples

### Create Collection

```bash
curl -X POST http://localhost:8108/collections \
  -H "X-TYPESENSE-API-KEY: 123" \
  -d '{
    "name": "movies",
    "fields": [
      {"name": "title", "type": "string"},
      {"name": "overview", "type": "string"},
      {"name": "embedding", "type": "float[]", "num_dim": 768}
    ],
    "default_sorting_field": "tmdb_id"
  }'
```

### Add Document

```bash
curl -X POST http://localhost:8108/collections/movies/documents \
  -H "X-TYPESENSE-API-KEY: 123" \
  -d '{
    "id": "603",
    "title": "The Matrix",
    "overview": "Set in the 22nd century..."
  }'
```

### Update Document

```bash
curl -X PATCH http://localhost:8108/collections/movies/documents/603 \
  -H "X-TYPESENSE-API-KEY: 123" \
  -d '{
    "overview": "Updated overview..."
  }'
```

### Delete Document

```bash
curl -X DELETE http://localhost:8108/collections/movies/documents/603 \
  -H "X-TYPESENSE-API-KEY: 123"
```

### Delete Collection

```bash
curl -X DELETE http://localhost:8108/collections/movies \
  -H "X-TYPESENSE-API-KEY: 123"
```

## Best Practices

1. **Use auto-embedding**: Let Typesense generate embeddings automatically
2. **Enable facets wisely**: Only facet fields you'll filter/aggregate on
3. **Set proper field types**: Use correct types for optimal storage
4. **Batch imports**: Import in batches of 100-1000 for speed
5. **Monitor memory**: Ensure enough RAM for indexes
6. **Use connection pooling**: Reuse HTTP connections
7. **Enable caching**: Use built-in query cache
8. **Tune typo tolerance**: Adjust based on your use case
9. **Use infix sparingly**: Only enable on fields that need it
10. **Regular backups**: Export data regularly

## Next Steps

- [Symfony AI Guide]({% link symfony-ai.md %}) - Compare with PostgreSQL
- [Performance Comparison]({% link comparison.md %}) - Benchmarks
- [Architecture]({% link architecture.md %}) - Deep dive
