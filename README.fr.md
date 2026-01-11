# Comparaison Recherche Hybride: Symfony AI vs Typesense

> [🇬🇧 English version](README.md) | [📚 Documentation complète](https://ahmed-bhs.github.io/symfony-hybrid-search-comparison-postgres-typesense/)

Comparaison de deux implémentations de recherche hybride sur une base de données de films (31 944 films):
- **Symfony AI HybridStore**: PostgreSQL + pgvector + algorithme RRF
- **Typesense**: Moteur de recherche avec recherche vectorielle intégrée

Les deux solutions combinent recherche sémantique (embeddings), recherche plein-texte (mots-clés) et matching flou (fautes de frappe).

## Pourquoi Cette Comparaison?

Ce projet démontre des implémentations réelles de recherche hybride avec le même dataset, vous permettant de:
- **Comparer les performances** entre PostgreSQL+pgvector et Typesense
- **Comprendre les compromis** (flexibilité vs. facilité d'utilisation, coût vs. performance)
- **Choisir la bonne solution** pour votre cas d'usage
- **Apprendre les concepts** de recherche hybride avec des exemples concrets

## Comparaison Rapide

| Caractéristique | Symfony AI HybridStore | Typesense |
|-----------------|------------------------|-----------|
| **Backend** | PostgreSQL + pgvector | Moteur de recherche dédié |
| **Algorithme** | RRF personnalisé (Reciprocal Rank Fusion) | Recherche hybride intégrée |
| **Setup** | Plus complexe (plusieurs extensions) | Plus simple (service unique) |
| **Flexibilité** | Accès SQL complet, algorithmes personnalisés | API-based, fonctionnalités prédéfinies |
| **Coût** | Gratuit (PostgreSQL open source) | Gratuit (self-hosted) ou Cloud |
| **Idéal pour** | Requêtes complexes, PostgreSQL existant | Configuration rapide, solution managée |

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                 Application Symfony 7.3                     │
│                                                             │
│  ┌────────────────────────┐  ┌──────────────────────────┐  │
│  │  Symfony AI HybridStore│  │      Typesense           │  │
│  │                        │  │                          │  │
│  │  ┌──────────────────┐ │  │  ┌────────────────────┐  │  │
│  │  │ Vector (pgvector)│ │  │  │  Recherche Vector  │  │  │
│  │  │ FTS (ts_rank)    │ │  │  │  Recherche Texte   │  │  │
│  │  │ Fuzzy (pg_trgm)  │ │  │  │  Matching Flou     │  │  │
│  │  │ Algo RRF         │ │  │  │  Hybride Intégré   │  │  │
│  │  └────────┬─────────┘ │  │  └─────────┬──────────┘  │  │
│  └───────────┼───────────┘  └────────────┼─────────────┘  │
│              │                            │                 │
│  ┌───────────▼────────────────────────────▼──────────────┐ │
│  │              Ollama (nomic-embed-text)                │ │
│  │              Embeddings partagés (768 dimensions)     │ │
│  └───────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘

Données:  PostgreSQL (table movies)      Typesense (collection movies)
          31 944 films avec embeddings   31 944 films avec embeddings
```

## Fonctionnalités

### Symfony AI HybridStore
- Implémentation RRF personnalisée (poids configurables)
- Accès direct PostgreSQL pour requêtes complexes
- Contrôle total sur l'algorithme de ranking
- semantic_ratio configurable (0.0 à 1.0)
- Filtrage avancé avec SQL
- Intégré avec Doctrine ORM

### Typesense
- Recherche hybride intégrée (auto-tunée)
- API RESTful (indépendant du langage)
- Embeddings auto-générés
- Tolérance aux fautes intégrée
- Support de recherche facettée
- Plus facile à scaler horizontalement

## Démarrage Rapide

### Prérequis
- Docker et Docker Compose
- 8GB RAM minimum (16GB recommandé)
- 4 CPU cores minimum

### 1. Cloner et Setup

```bash
git clone https://github.com/ahmed-bhs/symfony-hybrid-search-comparison-postgres-typesense.git
cd symfony-hybrid-search-comparison-postgres-typesense

# Démarrer tous les services (PostgreSQL, Typesense, Ollama)
./docker-setup.sh
```

Le script va:
- Démarrer PostgreSQL 16 + pgvector (port 5432)
- Démarrer Typesense 27.1 (port 8108)
- Démarrer Ollama avec nomic-embed-text (port 11434)
- Vérifier que tous les services sont prêts

### 2. Importer les Films

**Pour Symfony AI (PostgreSQL):**
```bash
# Test rapide (1000 films)
php bin/console app:import-movies --reset --limit=1000 --batch-size=50

# Dataset complet (31 944 films - ~40 minutes)
php bin/console app:import-movies --reset --batch-size=50
```

**Pour Typesense:**
```bash
# Import et génération automatique des embeddings
php bin/console app:typesense-index --reset

# Typesense génère les embeddings via Ollama automatiquement
```

### 3. Démarrer le Serveur Symfony

```bash
symfony server:start
```

### 4. Accès aux Interfaces

- **Interface Symfony AI**: http://localhost:8000
- **Interface Typesense**: http://localhost:8000/typesense
- **Endpoints API**:
  - Symfony AI: `GET /api/search?q=query`
  - Typesense: `GET /api/typesense/search?q=query`

## Exemples de Recherche

### Recherche Sémantique (Compréhension de Concept)

Trouver Shrek sans connaître le titre:

```bash
# Symfony AI
curl "http://localhost:8000/api/search?q=green+ogre+living+in+swamp&limit=5"

# Typesense
curl "http://localhost:8000/api/typesense/search?q=green+ogre+living+in+swamp&limit=5"
```

**Les deux retournent:** Shrek en premier résultat, démontrant la compréhension sémantique.

### Recherche par Mots-clés

```bash
# Symfony AI
curl "http://localhost:8000/api/search?q=fairy+tale&limit=5"

# Typesense
curl "http://localhost:8000/api/typesense/search?q=fairy+tale&limit=5"
```

**Résultats:**
- Pan's Labyrinth (a "fairy tale" 2x dans les keywords)
- Shrek 2 (a "fairy" 3x incluant "Fairy Godmother")
- Edward Scissorhands, Hook, Shrek...

### Matching Flou (Tolérance aux Fautes)

```bash
# Symfony AI
curl "http://localhost:8000/api/search?q=Batmn&limit=3"

# Typesense
curl "http://localhost:8000/api/typesense/search?q=Batmn&limit=3"
```

**Les deux trouvent:** "Batman" malgré la faute de frappe.

### Recherche par Acteur/Personnage

```bash
# Recherche des films d'Eddie Murphy
curl "http://localhost:8000/api/search?q=Eddie+Murphy&limit=5"
```

**Résultats:** Beverly Hills Cop, 48 Hrs., Trading Places, Dreamgirls, Shrek

## Configuration

### Symfony AI (config/packages/symfony_ai.yaml)

```yaml
ai:
    store:
        postgres:
            hybrid:
                dsn: 'pgsql:host=postgres;dbname=hybrid_search'
                semantic_ratio: 0.3        # 30% sémantique, 70% plein-texte
                text_search_strategy: 'bm25'
                rrf_k: 10
                normalize_scores: true
                fuzzy_enabled: true
                fuzzy_threshold: 0.3
```

**Paramètres Clés:**
- `semantic_ratio`: Balance entre vecteur (0.0) et texte (1.0)
- `text_search_strategy`: 'bm25' ou 'ts_rank'
- `rrf_k`: Constante RRF pour la fusion des rangs
- `fuzzy_threshold`: Similarité trigram (0.0-1.0)

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

**Fonctionnalités Clés:**
- Auto-embedding depuis Ollama
- Recherche infix activée pour correspondances partielles
- Recherche facettée sur genres et release_date

## Comparaison des Performances

### Vitesse d'Import (31 944 films)

| Solution | Temps | Vitesse |
|----------|-------|---------|
| **Symfony AI** | ~40 min | ~13 films/sec |
| **Typesense** | ~45 min | ~12 films/sec |

*Les deux utilisent Ollama avec 4 workers parallèles*

### Vitesse de Recherche (Moyenne)

| Type de Requête | Symfony AI | Typesense |
|-----------------|-----------|-----------|
| Mot-clé simple | 50-100ms | 30-80ms |
| Sémantique (vecteur) | 80-150ms | 50-120ms |
| Hybride (RRF) | 100-200ms | 60-150ms |

*Les résultats peuvent varier selon le matériel et la taille du dataset*

### Utilisation des Ressources

| Ressource | Symfony AI | Typesense |
|-----------|-----------|-----------|
| RAM (idle) | ~200MB (PostgreSQL) | ~500MB (Typesense) |
| RAM (indexé) | ~1.5GB | ~2GB |
| Espace disque | ~8GB | ~6GB |

## Avantages et Inconvénients

### Symfony AI HybridStore

**Avantages:**
- Contrôle total sur l'algorithme de ranking
- Pas de vendor lock-in (PostgreSQL standard)
- Requêtes SQL complexes possibles
- Intégré avec PostgreSQL existant
- Poids RRF configurables
- Pas de coût d'infrastructure supplémentaire

**Inconvénients:**
- Setup plus complexe (extensions, indexes)
- Tuning manuel requis
- Setup initial plus lent
- Scaling horizontal limité

### Typesense

**Avantages:**
- Setup et configuration plus faciles
- Fonctionnalités intégrées (facettes, geo-search)
- API RESTful (n'importe quel langage)
- Meilleur scaling horizontal
- Recherche hybride auto-tunée
- Excellente documentation

**Inconvénients:**
- Service supplémentaire à gérer
- Moins de contrôle sur les algorithmes
- Option cloud payante pour scaler
- Pas du SQL standard
- Infrastructure séparée requise

## Cas d'Usage

### Choisir Symfony AI HybridStore si:
- Vous utilisez déjà PostgreSQL
- Vous avez besoin de requêtes SQL complexes
- Vous voulez un contrôle total sur le ranking
- Vous construisez une solution personnalisée
- Budget serré (pas de services supplémentaires)
- Vous avez de l'expertise PostgreSQL

### Choisir Typesense si:
- Vous voulez un setup rapide
- Vous avez besoin d'une solution managée
- Vous préférez une approche API
- Vous avez besoin de scaling horizontal
- Vous voulez des fonctionnalités intégrées (facettes, etc.)
- Vous avez une architecture microservices

## Comparaison des API

### Requête de Recherche

**Symfony AI:**
```bash
GET /api/search?q=matrix&limit=10
```

**Typesense:**
```bash
GET /api/typesense/search?q=matrix&limit=10
```

### Format de Réponse

Les deux retournent:
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

## Commandes

```bash
# Symfony AI (PostgreSQL)
php bin/console app:import-movies --reset --limit=1000
php bin/console app:import-movies --reset  # Import complet

# Typesense
php bin/console app:typesense-index --reset

# Accès base de données
docker exec -it postgres_hybrid_search psql -U postgres -d hybrid_search

# API Typesense
curl "http://localhost:8108/collections/movies/documents/search?q=matrix&query_by=title,overview"

# Logs des services
docker logs -f postgres_hybrid_search
docker logs -f typesense_search
docker logs -f ollama_embeddings
```

## Dépannage

### Problèmes PostgreSQL
```bash
# Vérifier si pgvector est installé
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "SELECT * FROM pg_extension WHERE extname = 'vector';"

# Recréer les extensions
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "
  CREATE EXTENSION IF NOT EXISTS vector;
  CREATE EXTENSION IF NOT EXISTS pg_trgm;
"
```

### Problèmes Typesense
```bash
# Vérifier la santé
curl http://localhost:8108/health

# Voir les collections
curl -H "X-TYPESENSE-API-KEY: 123" http://localhost:8108/collections

# Supprimer la collection
curl -X DELETE -H "X-TYPESENSE-API-KEY: 123" http://localhost:8108/collections/movies
```

### Problèmes Ollama
```bash
# Vérifier le modèle
docker exec ollama_embeddings ollama list

# Re-télécharger le modèle
docker exec ollama_embeddings ollama pull nomic-embed-text

# Tester l'embedding
curl http://localhost:11434/api/embeddings -d '{
  "model": "nomic-embed-text",
  "prompt": "test"
}'
```

## Documentation

### Symfony AI
- [Documentation Symfony AI](https://github.com/symfony/ai)
- [pgvector](https://github.com/pgvector/pgvector)
- [Article RRF Algorithm](https://plg.uwaterloo.ca/~gvcormac/cormacksigir09-rrf.pdf)

### Typesense
- [Documentation Typesense](https://typesense.org/docs/)
- [Guide Vector Search](https://typesense.org/docs/guide/vector-search.html)
- [Hybrid Search](https://typesense.org/docs/guide/semantic-search.html)

### Général
- [Ollama](https://ollama.ai/)
- [nomic-embed-text](https://huggingface.co/nomic-ai/nomic-embed-text-v1)

## Dataset

**Source:** 31 944 films de TMDb
**Champs:**
- title, overview, genres
- release_date, poster
- Métadonnées TMDb (keywords, cast, director)

**Enrichissements:**
- Embeddings vectoriels (768 dimensions)
- Index plein-texte
- Index trigram pour recherche floue

## License

MIT

## Crédits

- **Symfony AI** - [symfony/ai](https://github.com/symfony/ai)
- **Typesense** - [typesense.org](https://typesense.org/)
- **Dataset** - TMDb (The Movie Database)
- **Embeddings** - [Ollama](https://ollama.ai/) avec nomic-embed-text
- **Extensions PostgreSQL** - [pgvector](https://github.com/pgvector/pgvector), pg_trgm
