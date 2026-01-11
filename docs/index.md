---
layout: default
title: Home
nav_order: 1
description: "Compare Symfony AI HybridStore vs Typesense for hybrid search in a real-world movie database"
permalink: /
---

# Hybrid Search Comparison
{: .fs-9 }

Compare two hybrid search implementations for a movie database with 31,944 movies.
{: .fs-6 .fw-300 }

[Get started now](#quick-start){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[View on GitHub](https://github.com/ahmed-bhs/symfony-hybrid-search-comparison-postgres-typesense){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## What is This?

This project demonstrates two real-world implementations of **hybrid search** using the same movie dataset:

- **Symfony AI HybridStore**: PostgreSQL + pgvector + custom RRF algorithm
- **Typesense**: Dedicated search engine with built-in vector search

Both solutions combine:
- **Semantic search** (embeddings) - understand concepts like "green ogre in swamp" → Shrek
- **Full-text search** (keywords) - traditional text matching
- **Fuzzy matching** (typos) - handle "Batmn" → "Batman"

{: .note }
> This is a practical comparison tool to help you choose the right hybrid search solution for your project.

## Why Compare These Solutions?

When building a search feature, you face a critical choice:

| Symfony AI HybridStore | Typesense |
|:----------------------|:----------|
| Use your existing PostgreSQL | Add a dedicated search engine |
| Full control, more complexity | Simpler setup, less control |
| Free (open source) | Free (self-hosted) or Cloud |
| Best for: Custom solutions | Best for: Quick deployment |

This project lets you:
- **Test both solutions** with the same data
- **Compare performance** in real conditions
- **Understand trade-offs** before committing
- **Learn hybrid search** with working examples

## Key Features

### Symfony AI HybridStore
- Custom RRF (Reciprocal Rank Fusion) implementation
- Configurable semantic vs keyword balance (semantic_ratio)
- Direct SQL access for complex queries
- Integrated with Doctrine ORM
- No vendor lock-in

### Typesense
- Built-in hybrid search (auto-tuned)
- RESTful API (language-agnostic)
- Auto-generated embeddings
- Built-in faceted search
- Easier horizontal scaling

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
```

### 3. Start Symfony Server

```bash
symfony server:start
```

### 4. Access Interfaces

- **Symfony AI Interface**: [http://localhost:8000](http://localhost:8000)
- **Typesense Interface**: [http://localhost:8000/typesense](http://localhost:8000/typesense)

## Example Searches

### Semantic Understanding

Find Shrek without knowing the title:

```bash
# Symfony AI
curl "http://localhost:8000/api/search?q=green+ogre+living+in+swamp"

# Typesense
curl "http://localhost:8000/api/typesense/search?q=green+ogre+living+in+swamp"
```

**Both return:** Shrek as the top result

### Typo Tolerance

```bash
curl "http://localhost:8000/api/search?q=Batmn"
```

**Finds:** "Batman" despite the typo

### Keyword Search

```bash
curl "http://localhost:8000/api/search?q=fairy+tale"
```

**Returns:** Pan's Labyrinth, Shrek 2, Edward Scissorhands...

## Performance Overview

| Metric | Symfony AI | Typesense |
|:-------|:-----------|:----------|
| **Import Time** (31,944 movies) | ~40 min | ~45 min |
| **Simple Search** | 50-100ms | 30-80ms |
| **Semantic Search** | 80-150ms | 50-120ms |
| **Hybrid Search** | 100-200ms | 60-150ms |
| **RAM Usage** | ~1.5GB | ~2GB |

## When to Use Each Solution

### Choose Symfony AI HybridStore if:
- ✅ You already use PostgreSQL
- ✅ You need complex SQL queries
- ✅ You want full control over ranking
- ✅ Budget is tight (no additional services)
- ✅ You have PostgreSQL expertise

### Choose Typesense if:
- ✅ You want quick setup
- ✅ You need a managed solution
- ✅ You prefer API-based approach
- ✅ You need horizontal scaling
- ✅ You want built-in features (facets, geo-search)

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
```

## Dataset

**31,944 movies from TMDb** with:
- Title, overview, genres
- Release date, poster
- Keywords, cast, director
- Vector embeddings (768 dimensions)

## Next Steps

- [Quick Start Guide]({% link quick-start.md %}) - Detailed setup instructions
- [Architecture]({% link architecture.md %}) - Deep dive into both implementations
- [Symfony AI Guide]({% link symfony-ai.md %}) - Configure and use HybridStore
- [Typesense Guide]({% link typesense.md %}) - Configure and use Typesense
- [Comparison]({% link comparison.md %}) - Detailed performance analysis

## License

MIT

## Credits

- **Symfony AI** - [symfony/ai](https://github.com/symfony/ai)
- **Typesense** - [typesense.org](https://typesense.org/)
- **Dataset** - TMDb (The Movie Database)
- **Embeddings** - [Ollama](https://ollama.ai/) with nomic-embed-text
