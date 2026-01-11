---
layout: default
title: Quick Start
nav_order: 2
description: "Get started with Hybrid Search Comparison in 5 minutes"
---

# Quick Start
{: .no_toc }

Get both solutions running and compare them in under 10 minutes.
{: .fs-6 .fw-300 }

## Table of contents
{: .no_toc .text-delta }

1. TOC
{:toc}

---

## Prerequisites

Before you begin, ensure you have:

- **Docker** and **Docker Compose** installed
- **PHP 8.2** or higher
- **Composer** installed
- **Symfony CLI** (recommended)
- **8GB RAM** minimum (16GB recommended)
- **4 CPU cores** minimum
- **20GB free disk space**

## Installation

### Step 1: Clone the Repository

```bash
git clone https://github.com/ahmed-bhs/symfony-hybrid-search-comparison-postgres-typesense.git
cd symfony-hybrid-search-comparison-postgres-typesense
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

### Step 3: Start Docker Services

The `docker-setup.sh` script will start all required services:

```bash
./docker-setup.sh
```

This script will:
1. Start **PostgreSQL 16** with pgvector extension (port 5432)
2. Start **Typesense 27.1** (port 8108)
3. Start **Ollama** with nomic-embed-text model (port 11434)
4. Verify all services are healthy
5. Create necessary database extensions

{: .note }
> The first run will download Docker images (~2GB) and the embedding model (~274MB).

### Step 4: Verify Services

Check that all services are running:

```bash
# PostgreSQL
docker exec postgres_hybrid_search psql -U postgres -c "SELECT version();"

# Typesense
curl http://localhost:8108/health

# Ollama
curl http://localhost:11434/api/tags
```

## Import Data

You can import a small subset for testing or the full dataset.

### Quick Test (1000 movies - ~3 minutes)

**Symfony AI (PostgreSQL):**
```bash
php bin/console app:import-movies --reset --limit=1000 --batch-size=50
```

**Typesense:**
```bash
php bin/console app:typesense-index --reset
```

{: .highlight }
> With 1000 movies, you can already test all features and compare both solutions!

### Full Import (31,944 movies - ~40-45 minutes)

**Symfony AI (PostgreSQL):**
```bash
php bin/console app:import-movies --reset --batch-size=50
```

**Typesense:**
```bash
php bin/console app:typesense-index --reset
```

{: .note }
> Both imports use Ollama to generate embeddings. The process is slow because each movie needs a semantic embedding (768 dimensions). We use 4 parallel workers to speed things up.

### Import Progress

You'll see progress like this:

```
Importing movies...
[============================] 1000/31944 (3%)
Processing time: 2.5 min
Speed: ~13 movies/sec
Estimated remaining: 40 min
```

## Start the Application

### With Symfony CLI (Recommended)

```bash
symfony server:start
```

### With PHP Built-in Server

```bash
php -S localhost:8000 -t public/
```

## Access the Interfaces

Once the server is running:

- **Symfony AI Interface**: [http://localhost:8000](http://localhost:8000)
- **Typesense Interface**: [http://localhost:8000/typesense](http://localhost:8000)

## Try Your First Search

### Using the Web Interface

1. Open [http://localhost:8000](http://localhost:8000)
2. Enter a search query: `green ogre living in swamp`
3. See Shrek appear as the top result
4. Switch to Typesense interface and try the same query
5. Compare results!

### Using the API

**Symfony AI:**
```bash
curl "http://localhost:8000/api/search?q=green+ogre+living+in+swamp&limit=5"
```

**Typesense:**
```bash
curl "http://localhost:8000/api/typesense/search?q=green+ogre+living+in+swamp&limit=5"
```

**Response:**
```json
{
  "query": "green ogre living in swamp",
  "hits": 5,
  "processingTimeMs": 120,
  "results": [
    {
      "id": 808,
      "title": "Shrek",
      "overview": "It ain't easy bein' green -- especially if you're a likable...",
      "score": 89.5,
      "releaseDate": "2001-05-18"
    }
  ]
}
```

## Example Queries to Try

### Semantic Search (Concept-based)

These queries don't use exact keywords but rely on semantic understanding:

```bash
# Find Shrek
curl "http://localhost:8000/api/search?q=green+ogre+living+in+swamp"

# Find The Matrix
curl "http://localhost:8000/api/search?q=simulated+reality+red+pill"

# Find Inception
curl "http://localhost:8000/api/search?q=dreams+within+dreams"

# Find Toy Story
curl "http://localhost:8000/api/search?q=toys+come+alive+when+humans+leave"
```

### Keyword Search

Traditional text matching:

```bash
# Movies with "fairy tale" in keywords
curl "http://localhost:8000/api/search?q=fairy+tale"

# Movies with "Eddie Murphy" in cast
curl "http://localhost:8000/api/search?q=Eddie+Murphy"

# Movies with "time travel" theme
curl "http://localhost:8000/api/search?q=time+travel"
```

### Fuzzy Search (Typo Tolerance)

These queries have typos but still find the right movies:

```bash
# "Batman" with typo
curl "http://localhost:8000/api/search?q=Batmn"

# "Superman" with typo
curl "http://localhost:8000/api/search?q=Supermn"

# "Inception" with typo
curl "http://localhost:8000/api/search?q=Inceptn"
```

## Verify the Setup

Run a quick verification to ensure everything is working:

```bash
# Check PostgreSQL extensions
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "
  SELECT extname, extversion FROM pg_extension
  WHERE extname IN ('vector', 'pg_trgm');
"
```

Expected output:
```
 extname | extversion
---------+------------
 vector  | 0.8.0
 pg_trgm | 1.6
```

```bash
# Check Typesense collection
curl -H "X-TYPESENSE-API-KEY: 123" http://localhost:8108/collections/movies
```

```bash
# Check number of imported movies
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "
  SELECT COUNT(*) as total_movies FROM movies;
"
```

## Common Issues

### Issue: Docker services won't start

**Solution:**
```bash
# Stop all containers
docker-compose down

# Remove volumes (will delete data!)
docker-compose down -v

# Start fresh
./docker-setup.sh
```

### Issue: Ollama model not found

**Solution:**
```bash
# Re-download the model
docker exec ollama_embeddings ollama pull nomic-embed-text

# Verify it's available
docker exec ollama_embeddings ollama list
```

### Issue: PostgreSQL extensions missing

**Solution:**
```bash
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "
  CREATE EXTENSION IF NOT EXISTS vector;
  CREATE EXTENSION IF NOT EXISTS pg_trgm;
"
```

### Issue: Typesense returns 404

**Solution:**
```bash
# Check if collection exists
curl -H "X-TYPESENSE-API-KEY: 123" http://localhost:8108/collections

# Re-create collection
php bin/console app:typesense-index --reset
```

### Issue: Import is too slow

**Causes:**
- Limited CPU/RAM
- Ollama running on CPU instead of GPU
- Too many parallel workers

**Solutions:**
```bash
# Reduce batch size
php bin/console app:import-movies --reset --batch-size=25

# Import smaller dataset first
php bin/console app:import-movies --reset --limit=5000
```

### Issue: Symfony server won't start

**Solution:**
```bash
# Check if port 8000 is in use
lsof -i :8000

# Use a different port
symfony server:start --port=8001
```

## View Logs

If you encounter issues, check the logs:

```bash
# PostgreSQL logs
docker logs postgres_hybrid_search

# Typesense logs
docker logs typesense_search

# Ollama logs
docker logs ollama_embeddings

# Follow logs in real-time
docker logs -f ollama_embeddings
```

## Test API Endpoints

### Symfony AI Endpoints

```bash
# Basic search
curl "http://localhost:8000/api/search?q=matrix"

# With limit
curl "http://localhost:8000/api/search?q=matrix&limit=10"

# Health check
curl "http://localhost:8000/api/health"
```

### Typesense Endpoints

```bash
# Basic search
curl "http://localhost:8000/api/typesense/search?q=matrix"

# Direct Typesense API
curl -H "X-TYPESENSE-API-KEY: 123" \
  "http://localhost:8108/collections/movies/documents/search?q=matrix&query_by=title,overview"

# Collection info
curl -H "X-TYPESENSE-API-KEY: 123" \
  http://localhost:8108/collections/movies
```

## Database Access

### PostgreSQL

```bash
# Connect to database
docker exec -it postgres_hybrid_search psql -U postgres -d hybrid_search

# Run queries
SELECT COUNT(*) FROM movies;
SELECT title, overview FROM movies LIMIT 5;
SELECT * FROM pg_extension WHERE extname = 'vector';

# Exit
\q
```

### Typesense

```bash
# View all collections
curl -H "X-TYPESENSE-API-KEY: 123" http://localhost:8108/collections

# Get specific document
curl -H "X-TYPESENSE-API-KEY: 123" \
  http://localhost:8108/collections/movies/documents/603

# Search with filters
curl -H "X-TYPESENSE-API-KEY: 123" \
  "http://localhost:8108/collections/movies/documents/search?q=action&filter_by=release_date:>2020"
```

## Next Steps

Now that everything is running:

1. **Compare Results**: Try the same queries on both interfaces
2. **Test Performance**: Use browser dev tools to compare response times
3. **Learn Configuration**: Read [Symfony AI Guide]({% link symfony-ai.md %}) and [Typesense Guide]({% link typesense.md %})
4. **Understand Architecture**: Check out [Architecture]({% link architecture.md %})
5. **See Detailed Comparison**: Read [Comparison]({% link comparison.md %})

## Cleanup

When you're done testing:

```bash
# Stop services
docker-compose down

# Remove data volumes (will delete all imported data!)
docker-compose down -v

# Remove all containers and images
docker-compose down --rmi all -v
```

{: .warning }
> `docker-compose down -v` will delete all imported movies. You'll need to re-import if you want to use the system again.
