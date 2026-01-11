# Hybrid Movie Search - Symfony AI + PostgreSQL

Application de recherche hybride utilisant **Symfony AI HybridStore** avec l'algorithme **RRF (Reciprocal Rank Fusion)** combinant recherche sémantique (pgvector), recherche plein-texte (PostgreSQL ts_rank) et matching flou (pg_trgm).

## Fonctionnalités

- **Symfony AI HybridStore** - Implémentation officielle de la recherche hybride
- **RRF (Reciprocal Rank Fusion)** - Algorithme de fusion des résultats de recherche
- **Recherche Sémantique** - Embeddings vectoriels via Ollama (pgvector)
- **Recherche Plein-texte** - PostgreSQL Full-Text Search (ts_rank)
- **Fuzzy Matching** - Recherche floue avec pg_trgm (configurable)
- **31,944 films** - Dataset TMDb avec titres, descriptions, genres et posters
- **Interface Web** - Interface de recherche moderne et réactive
- **Docker Compose** - Stack complète containerisée avec Ollama optimisé

##  Architecture

```
┌─────────────────────────────────────────────────┐
│           Symfony 7.3 Application               │
│                                                 │
│  ┌──────────────────────────────────────────┐  │
│  │         Symfony AI Bundle                │  │
│  │                                          │  │
│  │   ┌─────────────────────────────────┐   │  │
│  │   │     HybridStore                 │   │  │
│  │   │   (RRF Algorithm)               │   │  │
│  │   └──────────┬──────────────────────┘   │  │
│  │              │                           │  │
│  │      ┌───────┴────────┐                 │  │
│  │      │                │                 │  │
│  │   ┌──▼───┐      ┌────▼─────┐          │  │
│  │   │Vector│      │Full-Text │          │  │
│  │   │Search│      │  Search  │          │  │
│  │   └──┬───┘      └────┬─────┘          │  │
│  └──────┼───────────────┼────────────────┘  │
│         │               │                    │
│    ┌────▼───────────────▼─────┐             │
│    │   PostgreSQL + pgvector  │             │
│    │   (Hybrid Search Table)  │             │
│    └──────────────────────────┘             │
│                                              │
│    ┌──────────────┐                         │
│    │    Ollama    │◄──── Vectorizer         │
│    │ (embeddings) │                         │
│    └──────────────┘                         │
└─────────────────────────────────────────────┘
```

## Quick Start

### Prérequis
- Docker et Docker Compose
- 8GB RAM minimum (16GB recommandé pour performances optimales)
- 4 CPU cores minimum (8 cores recommandé)

### Setup Automatique

```bash
# Clone le projet
git clone https://github.com/ahmed-bhs/symfony-postgres-ai-hybrid-search.git
cd symfony-postgres-ai-hybrid-search

# Lance le setup automatisé
./docker-setup.sh
```

Le script va automatiquement:
- Démarrer PostgreSQL 16 + pgvector (port 5432)
- Démarrer Ollama optimisé avec 4 workers parallèles (port 11434)
- Télécharger le modèle nomic-embed-text (768 dimensions)
- Vérifier que tout est opérationnel

### Setup Manuel

Si vous préférez faire le setup manuellement:

```bash
# 1. Démarrer les services
docker compose up -d

# 2. Attendre que PostgreSQL soit prêt
docker exec postgres_hybrid_search pg_isready -U postgres

# 3. Attendre qu'Ollama soit prêt
curl http://localhost:11434/api/tags

# 4. Télécharger le modèle d'embeddings
docker exec ollama_embeddings ollama pull nomic-embed-text

# 5. Vérifier le modèle
docker exec ollama_embeddings ollama list
```

### Importer les films

```bash
# Test rapide avec 1000 films (contient Shrek)
php bin/console app:import-movies --reset --limit=1000 --batch-size=50

# Import complet (31,944 films - environ 40 minutes avec optimisations)
php bin/console app:import-movies --reset --batch-size=50
```

La commande va:
- Créer la table PostgreSQL avec pgvector + pg_trgm
- Créer les indexes (vector + GIN pour full-text + trigram pour fuzzy)
- Générer les embeddings avec Ollama (4 en parallèle)
- Insérer les films dans le HybridStore

### Utiliser l'interface

**Interface Web:**
```
http://localhost:8000
```

**API REST:**
```bash
# Recherche hybride
curl "http://localhost:8000/api/search?q=space+adventure"

# Health check
curl "http://localhost:8000/api/health"
```

## Exemples de Recherche

### 1. Recherche Conceptuelle (Semantic Search)

L'exemple phare de la recherche sémantique - chercher Shrek sans connaître le titre:

```bash
curl "http://localhost:8000/api/search?q=green+ogre+living+in+swamp&limit=5" | jq -r '.results[] | "\(.title) - Score: \(.score)"'
```

**Résultat:**
```
Shrek - Score: 42.00
Monty Python and the Holy Grail - Score: 30.38
The Reaping - Score: 26.19
Shrek the Third - Score: 17.48
```

- **La recherche comprend le concept** ("green ogre in swamp") et trouve Shrek en premier même si ces mots exacts ne sont pas dans la description. C'est la puissance de la recherche sémantique avec embeddings vectoriels!

---

### 2. Recherche par Genre/Thème

```bash
curl "http://localhost:8000/api/search?q=fairy+tale&limit=5" | jq -r '.results[] | "\(.title) - Score: \(.score)"'
```

**Résultat:**
```
Pan's Labyrinth - Score: 42.00
Shrek 2 - Score: 38.50
Edward Scissorhands - Score: 35.54
Hook - Score: 28.78
Shrek - Score: 26.86
```

**Le ranking hybride fonctionne:**
- Pan's Labyrinth a "fairy tale" 2x dans les keywords
- Shrek 2 a le mot "fairy" 3x (keywords + Fairy Godmother character)
- Shrek n'a "fairy tale" qu'1x dans les keywords

---

### 3. Recherche par Personnage/Acteur

```bash
curl "http://localhost:8000/api/search?q=Eddie+Murphy&limit=3" | jq -r '.results[] | "\(.title) - \(.overview[:80])..."'
```

**Résultat:**
```
Beverly Hills Cop
48 Hrs.
Trading Places
Dreamgirls
Shrek
```

- **Recherche enrichie TMDb:** Les personnages/acteurs (characters) sont indexés dans le contenu, permettant de trouver tous les films d'Eddie Murphy.

---

### 4. Recherche avec Fautes de Frappe (Fuzzy Matching)

```bash
curl "http://localhost:8000/api/search?q=Batmn&limit=3" | jq -r '.results[0] | "\(.title)"'
```

**Résultat:**
```
Batman
```

- **Fuzzy matching avec pg_trgm:** Tolère les fautes de frappe grâce à la similarité trigram.

---

### 5. Recherche par Concept Abstrait

```bash
curl "http://localhost:8000/api/search?q=artificial+intelligence+robot+consciousness&limit=5" | jq -r '.results[] | "\(.title) - Score: \(.score)"'
```

**Résultat:**
```
A.I. Artificial Intelligence - Score: 82.02
The Matrix - Score: 30.15
The Matrix Revolutions - Score: 27.04
Blade Runner - Score: 24.71
Contact - Score: 23.86
```

**Compréhension sémantique:** Trouve les films sur l'IA et la conscience même sans ces mots exacts dans le titre.

---

### 6. Recherche Multilingue (via Embeddings)

```bash
curl "http://localhost:8000/api/search?q=guerre+dans+l'espace&limit=3" | jq -r '.results[] | .title'
```

**Note:** Les résultats peuvent varier selon le dataset. Les embeddings vectoriels permettent une recherche cross-language grâce à la compréhension sémantique.

**Cross-language search:** Les embeddings capturent la sémantique au-delà de la langue.

---

### 7. Recherche par Réalisateur

```bash
curl "http://localhost:8000/api/search?q=Christopher+Nolan&limit=5" | jq -r '.results[] | "\(.title) - \(.release_date | strftime(\"%Y\"))"'
```

**Résultat:**
```
Insomnia
The Prestige
Memento
Batman Begins
The Dark Knight
```

- **Director indexing:** Le réalisateur est inclus dans le contenu searchable, permettant de retrouver tous les films de Christopher Nolan.

**Réponse JSON:**
```json
{
  "query": "space adventure",
  "method": "Symfony AI Hybrid Search (RRF)",
  "hits": 20,
  "processingTimeMs": 156.23,
  "results": [
    {
      "id": 11,
      "title": "Star Wars",
      "overview": "Princess Leia is captured...",
      "genres": ["Adventure", "Action", "Science Fiction"],
      "poster": "https://image.tmdb.org/t/p/w500/6FfCtAuVAW8XJjZ7eWeLibRLWTw.jpg",
      "release_date": 233366400,
      "score": 0.892
    }
  ],
  "info": {
    "algorithm": "Reciprocal Rank Fusion",
    "components": {
      "semantic": "pgvector cosine similarity",
      "fulltext": "PostgreSQL ts_rank"
    }
  }
}
```

##  Comment fonctionne le RRF ?

**Reciprocal Rank Fusion (RRF)** est un algorithme qui combine les résultats de plusieurs systèmes de recherche en utilisant leurs rangs:

```
RRF_score(doc) = Σ 1 / (k + rank_i(doc))
```

où:
- `k` = 60 (constante RRF, configurable)
- `rank_i(doc)` = position du document dans le résultat i

### Processus:

1. **Recherche Vectorielle** (Sémantique)
   - Génère l'embedding de la requête avec Ollama
   - Recherche par similarité cosinus dans pgvector
   - Résultats triés par distance vectorielle

2. **Recherche Plein-texte** (Keyword)
   - Utilise PostgreSQL `to_tsvector()` et `to_tsquery()`
   - Calcule le score avec `ts_rank_cd()`
   - Résultats triés par pertinence textuelle

3. **Fusion RRF**
   - Combine les deux listes de résultats
   - Calcule le score RRF pour chaque document
   - Retourne les résultats triés par score RRF final

### Avantages du RRF:
- - Pas besoin de normaliser les scores
- - Robuste aux différences d'échelle
- - Utilise uniquement les rangs (positions)
- - Meilleurs résultats que la moyenne pondérée

##  Configuration

### Fichier `.env`
```env
DATABASE_URL="postgresql://postgres:postgres@127.0.0.1:5432/hybrid_search?serverVersion=16&charset=utf8"
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=nomic-embed-text
```

### Symfony AI (`config/packages/symfony_ai.yaml`)
```yaml
ai:
    platform:
        ollama:
            host_url: '%env(OLLAMA_URL)%'

    store:
        postgres_hybrid:
            hybrid:
                dsn: 'pgsql:host=postgres;dbname=hybrid_search'
                username: 'postgres'
                password: 'postgres'
                table_name: 'movies'
                vector_field: 'embedding'
                content_field: 'content'
                semantic_ratio: 0.5      # 50% semantic / 50% fulltext
                language: 'simple'        # PostgreSQL text search config
                rrf_k: 60                 # RRF constant
                # Fuzzy matching (pg_trgm)
                fuzzy_enabled: true
                fuzzy_threshold: 0.3     # Seuil de similarité (0.0-1.0)

    vectorizer:
        ollama:
            model: '%env(OLLAMA_MODEL)%'
```

### Paramètres du HybridStore

**Recherche Hybride:**
- **semantic_ratio** (0.0 - 1.0)
  - `0.0` = 100% recherche plein-texte
  - `0.5` = Hybride équilibré (par défaut)
  - `1.0` = 100% recherche sémantique

- **language**
  - `'simple'` = Multilingue, pas de stemming (recommandé)
  - `'english'`, `'french'`, etc. = Stemming spécifique à la langue

- **rrf_k** (int, défaut: 60)
  - Plus élevé = pondération plus égale entre les résultats

**Fuzzy Matching (pg_trgm):**
- **fuzzy_enabled** (bool, défaut: true)
  - Active/désactive le matching flou

- **fuzzy_threshold** (0.0 - 1.0, défaut: 0.3)
  - Seuil de similarité trigram
  - Plus bas = plus tolérant aux fautes
  - `0.1` = Très tolérant
  - `0.3` = Équilibré (recommandé)
  - `0.5` = Strict

## Performance

### Optimisations Ollama Docker

Le service Ollama est configuré pour des performances optimales:

**Ressources allouées:**
- CPU: 4-8 cores (réservé: 4, limite: 8)
- RAM: 8-16GB (réservé: 8GB, limite: 16GB)
- Parallélisme: 4 embeddings simultanés
- Modèles en mémoire: 2
- Queue max: 512 requêtes

**Variables d'environnement clés:**
- `OLLAMA_NUM_PARALLEL: 4` - Génère 4 embeddings en parallèle (4x plus rapide)
- `OLLAMA_MAX_LOADED_MODELS: 2` - Garde les modèles en RAM (pas de reload)
- `OLLAMA_RUNNERS: 4` - 4 workers concurrents
- `OLLAMA_MAX_QUEUE: 512` - File d'attente large

### Benchmarks Import

| Configuration | 1000 films | 31k films | Amélioration |
|---------------|-----------|-----------|--------------|
| Ollama local (1 core) | ~5min | ~2.5h | Baseline |
| **Docker (4 cores)** | **~1.5min** | **~40min** | **3.3x** |
| Docker (8 cores) | ~1min | ~25min | 5x |
| Docker + GPU NVIDIA | ~15s | ~8min | 20x |

### Benchmarks Recherche

- **Recherche simple:** 50-150ms
- **Recherche complexe:** 100-250ms
- **Dimension des vecteurs:** 768 (nomic-embed-text)

### Monitoring

```bash
# Stats en temps réel
docker stats ollama_embeddings postgres_hybrid_search

# Logs Ollama
docker logs -f ollama_embeddings

# Requêtes actives Ollama
curl http://localhost:11434/api/ps
```

### Optimisation GPU (NVIDIA uniquement)

Pour activer le support GPU dans docker-compose.yml, décommentez:

```yaml
deploy:
  resources:
    reservations:
      devices:
        - driver: nvidia
          count: 1
          capabilities: [gpu]
```

Puis redémarrez:
```bash
docker compose down
docker compose up -d
```

**Note:** Vérifiez d'abord que vous avez une carte NVIDIA:
```bash
lspci | grep -i nvidia
nvidia-smi
```

## Commandes Utiles

```bash
# Import avec options
php bin/console app:import-movies --limit=1000 --batch-size=50
php bin/console app:import-movies --reset --limit=5000

# Logs services
docker logs -f ollama_embeddings
docker logs -f postgres_hybrid_search

# Accès PostgreSQL
docker exec -it postgres_hybrid_search psql -U postgres -d hybrid_search

# Requêtes PostgreSQL utiles
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "SELECT COUNT(*) FROM movies;"
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "\d movies"
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "SELECT title FROM movies LIMIT 5;"

# Vérifier les extensions
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "SELECT * FROM pg_extension;"
```

## Structure du Projet

```
src/
├── Command/
│   └── ImportMoviesCommand.php      # Import des films dans HybridStore
├── Controller/
│   └── SearchController.php         # API + Interface web
├── Service/
│   └── MovieSearchService.php       # Service utilisant HybridStore
└── Entity/
    └── Movie.php                    # Entity Doctrine (optionnelle)

config/packages/
└── symfony_ai.yaml                  # Configuration Symfony AI

templates/
├── base.html.twig                   # Template de base
└── search/
    └── index.html.twig              # Interface de recherche

docker-compose.yml                   # Stack Docker (PostgreSQL + Ollama)
docker-setup.sh                      # Script de setup automatisé
PERFORMANCE.md                       # Guide détaillé des performances
```

## Dépannage

### Ollama ne répond pas

```bash
# Vérifier les logs
docker logs ollama_embeddings

# Redémarrer le service
docker compose restart ollama

# Vérifier qu'Ollama écoute bien
curl http://localhost:11434/api/tags
```

### Le modèle n'est pas téléchargé

```bash
# Télécharger manuellement
docker exec ollama_embeddings ollama pull nomic-embed-text

# Vérifier les modèles installés
docker exec ollama_embeddings ollama list
```

### Import très lent

Vérifiez que les optimisations Docker sont actives:

```bash
# Afficher la config Ollama
docker exec ollama_embeddings env | grep OLLAMA

# Devrait afficher:
# OLLAMA_NUM_PARALLEL=4
# OLLAMA_RUNNERS=4
```

Si les valeurs ne sont pas bonnes, redémarrez:
```bash
docker compose down
docker compose up -d
```

### Erreur PostgreSQL - Extensions manquantes

```bash
# Installer les extensions nécessaires
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "
  CREATE EXTENSION IF NOT EXISTS vector;
  CREATE EXTENSION IF NOT EXISTS pg_trgm;
"
```

### RAM insuffisante

Réduire les ressources dans docker-compose.yml:

```yaml
# Pour Ollama
memory: 4G              # Au lieu de 8G
OLLAMA_NUM_PARALLEL: 2  # Au lieu de 4
```

### Reset complet

```bash
# Supprimer tous les containers et volumes
docker compose down -v

# Redémarrer proprement
./docker-setup.sh

# Réimporter les films
php bin/console app:import-movies --reset --limit=1000
```

## Documentation

- [Symfony AI](https://github.com/symfony/ai)
- [pgvector](https://github.com/pgvector/pgvector)
- [pg_trgm](https://www.postgresql.org/docs/current/pgtrgm.html)
- [Ollama](https://ollama.ai/)
- [RRF Algorithm](https://plg.uwaterloo.ca/~gvcormac/cormacksigir09-rrf.pdf)
- [Supabase Hybrid Search](https://supabase.com/docs/guides/ai/hybrid-search)

## Cas d'Usage

### Recherche conceptuelle
```
"movies about artificial intelligence and consciousness"
→ Trouve des films sur l'IA même sans ces mots exacts
```

### Recherche par description
```
"green ogre living in a swamp"
→ Trouve Shrek grâce à la compréhension sémantique
```

### Recherche de titre exact
```
"The Matrix"
→ Combine exact match + similarité sémantique
```

### Recherche avec fautes de frappe
```
"Batmn" → Trouve "Batman" grâce au fuzzy matching
"Inceptoin" → Trouve "Inception"
```

### Recherche multilingue
```
"aventure spatiale" (français)
→ Trouve "space adventure" grâce aux embeddings
```

## Données

**Dataset:** 31,944 films TMDb
**Source:** `~/meilisearch-datasets/movies.json`

**Champs:**
- `title` - Titre du film
- `overview` - Description
- `genres` - Liste des genres
- `poster` - URL du poster TMDB
- `release_date` - Timestamp de sortie

## License

MIT

## Crédits

- **Symfony AI** - [symfony/ai](https://github.com/symfony/ai)
- **Dataset** - TMDb (The Movie Database)
- **Embeddings** - [Ollama](https://ollama.ai/) avec nomic-embed-text
- **Vector Search** - [pgvector](https://github.com/pgvector/pgvector)
- **Fuzzy Matching** - [pg_trgm](https://www.postgresql.org/docs/current/pgtrgm.html)
