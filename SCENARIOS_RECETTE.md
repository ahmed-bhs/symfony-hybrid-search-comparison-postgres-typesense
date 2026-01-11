# Scénarios de Recette - Hybrid Movie Search

Application de recherche de films utilisant Symfony AI avec PostgreSQL HybridStore (pgvector + BM25 + RRF)

## 🌐 Informations

- **URL**: http://127.0.0.1:8000
- **Base de données**: 1000 films uniques
- **Store**: PostgreSQL HybridStore (pgvector + BM25)
- **Vectorizer**: Ollama (nomic-embed-text, 768 dimensions)
- **Algorithme**: Reciprocal Rank Fusion (RRF) avec semantic_ratio=0.1

## 📋 Configuration Active

```yaml
Semantic Ratio: 0.1 (10% vectoriel, 90% BM25)
Distance: Cosine
Language: English
BM25 Language: en
Text Search Strategy: BM25
RRF k: 10
Default Min Score: 0
Normalize Scores: true (0-100)

Fuzzy Matching:
- Primary Threshold: 0.25
- Secondary Threshold: 0.2
- Strict Threshold: 0.15
- Fuzzy Weight: 0.4

Searchable Attributes (boosts):
- title: 0.9
- genres: 1.0
- overview: 1.1
```

---

## 🧪 Scénario 1: Recherche Vectorielle Pure (Sémantique)

**Objectif**: Tester la recherche sémantique basée sur le sens, pas les mots-clés exacts

### Test 1.1: Recherche par concept
```bash
curl "http://127.0.0.1:8000/api/search?q=artificial+intelligence+robots&limit=5"
```

**Résultat attendu**:
- ✅ Films de science-fiction avec IA/robots
- ✅ Score > 0 (normalisé 0-100)
- ✅ Temps de réponse < 200ms
- ✅ Champs retournés: id, title, overview, genres, poster, release_date, score

**Validation**:
- Les films trouvés sont pertinents sémantiquement même si les mots exacts ne sont pas dans le titre
- Exemple: "The Matrix", "Ex Machina", "I, Robot"

### Test 1.2: Recherche par émotion/thème
```bash
curl "http://127.0.0.1:8000/api/search?q=scary+horror+frightening&limit=5"
```

**Résultat attendu**:
- ✅ Films d'horreur/thriller
- ✅ Correspondance sémantique même sans mots-clés exacts

---

## 🔍 Scénario 2: Recherche BM25 (Lexicale)

**Objectif**: Tester la recherche par mots-clés avec BM25 (dominant avec semantic_ratio=0.1)

### Test 2.1: Recherche par titre exact
```bash
curl "http://127.0.0.1:8000/api/search?q=matrix&limit=5"
```

**Résultat attendu**:
- ✅ "The Matrix" en premier
- ✅ Score élevé (> 80) pour correspondance exacte
- ✅ Autres films avec "matrix" dans le titre/overview

### Test 2.2: Recherche par répétition de mots-clés
```bash
curl "http://127.0.0.1:8000/api/search?q=love+love+romance&limit=5"
```

**Résultat attendu**:
- ✅ Films romantiques
- ✅ BM25 favorise les documents avec haute fréquence du terme "love"
- ✅ Saturation BM25 évite la sur-pondération

---

## 🎯 Scénario 3: Recherche Hybride (RRF)

**Objectif**: Tester la fusion des scores vectoriels et BM25 via RRF

### Test 3.1: Recherche mixte sémantique + lexicale
```bash
curl "http://127.0.0.1:8000/api/search?q=space+exploration+astronauts&limit=10"
```

**Résultat attendu**:
- ✅ Combinaison de films avec mots-clés exacts ET sens similaire
- ✅ Score RRF équilibré
- ✅ "Interstellar", "The Martian", "Gravity", "Apollo 13"
- ✅ `score_breakdown` présent avec détails (vector_rank, fts_rank, etc.)

**Validation du score_breakdown**:
```json
{
  "score_breakdown": {
    "vector_rank": 1,
    "fts_rank": 3,
    "vector_distance": 0.42,
    "fts_score": 15.2,
    "vector_contribution": 0.05,
    "fts_contribution": 0.85
  }
}
```

### Test 3.2: Recherche avec typo (Fuzzy Matching)
```bash
curl "http://127.0.0.1:8000/api/search?q=spiderman&limit=5"
```

**Puis avec typo**:
```bash
curl "http://127.0.0.1:8000/api/search?q=spidermen&limit=5"
```

**Résultat attendu**:
- ✅ Les deux requêtes trouvent "Spider-Man"
- ✅ Fuzzy matching (pg_trgm) compense les fautes d'orthographe
- ✅ Score légèrement plus bas avec typo

---

## 📊 Scénario 4: Pondération des Champs (Searchable Attributes)

**Objectif**: Vérifier que les boosts configurés sont appliqués

### Test 4.1: Mot dans le titre (boost 0.9)
```bash
curl "http://127.0.0.1:8000/api/search?q=inception&limit=5"
```

**Résultat attendu**:
- ✅ "Inception" en premier (mot dans le titre)
- ✅ Score très élevé

### Test 4.2: Mot dans overview (boost 1.1)
```bash
curl "http://127.0.0.1:8000/api/search?q=time+travel+paradox&limit=5"
```

**Résultat attendu**:
- ✅ Films avec "time travel" dans l'overview sont favorisés
- ✅ Boost de 1.1 donne légèrement plus de poids à l'overview

### Test 4.3: Mot dans genres (boost 1.0)
```bash
curl "http://127.0.0.1:8000/api/search?q=comedy&limit=5"
```

**Résultat attendu**:
- ✅ Comédies en premier
- ✅ Boost baseline de 1.0

---

## ⚖️ Scénario 5: Semantic Ratio (10% vectoriel, 90% BM25)

**Objectif**: Vérifier que BM25 domine avec semantic_ratio=0.1

### Test 5.1: Comparaison requête sémantique vs lexicale
```bash
# Requête avec mots-clés exacts
curl "http://127.0.0.1:8000/api/search?q=batman+gotham+joker&limit=5"

# Requête sémantique (concept)
curl "http://127.0.0.1:8000/api/search?q=superhero+vigilante+dark+city&limit=5"
```

**Résultat attendu**:
- ✅ Première requête (mots-clés) a scores plus élevés
- ✅ Deuxième requête (sémantique) trouve aussi des films pertinents mais scores plus bas
- ✅ BM25 domine (90%) donc les mots-clés exacts sont favorisés

---

## 🎚️ Scénario 6: Filtrage par Score Minimum

**Objectif**: Tester le filtrage par minScore

### Test 6.1: Sans filtre
```bash
curl "http://127.0.0.1:8000/api/search?q=action+movie&limit=20"
```

**Résultat attendu**:
- ✅ 20 résultats
- ✅ Scores variés (peuvent être très bas)

### Test 6.2: Avec filtre minScore (via modification du code ou config)
```bash
# Nécessite modification temporaire de default_min_score dans config
# Ou ajout d'un paramètre minScore dans l'API
```

**Résultat attendu**:
- ✅ Uniquement résultats avec score >= seuil
- ✅ Moins de résultats mais plus pertinents

---

## 🔢 Scénario 7: Normalisation des Scores (0-100)

**Objectif**: Vérifier que les scores sont normalisés

### Test 7.1: Vérification des scores
```bash
curl "http://127.0.0.1:8000/api/search?q=star+wars&limit=10" | jq '.results[].score'
```

**Résultat attendu**:
- ✅ Tous les scores sont entre 0 et 100
- ✅ Scores plus élevés = meilleure pertinence
- ✅ Normalisation facilite l'interprétation

---

## 🚀 Scénario 8: Performance et Scalabilité

**Objectif**: Mesurer les performances

### Test 8.1: Temps de réponse
```bash
time curl "http://127.0.0.1:8000/api/search?q=adventure&limit=50"
```

**Résultat attendu**:
- ✅ Temps de réponse < 200ms pour 50 résultats
- ✅ `processingTimeMs` dans la réponse JSON

### Test 8.2: Requêtes concurrentes
```bash
# Utiliser Apache Bench ou wrk
ab -n 100 -c 10 "http://127.0.0.1:8000/api/search?q=thriller&limit=10"
```

**Résultat attendu**:
- ✅ Toutes les requêtes réussissent
- ✅ Temps de réponse moyen < 300ms
- ✅ Pas d'erreurs

### Test 8.3: Recherche avec limit élevé
```bash
curl "http://127.0.0.1:8000/api/search?q=movie&limit=100"
```

**Résultat attendu**:
- ✅ Retourne 100 résultats
- ✅ Temps acceptable (< 500ms)
- ✅ Déduplication correcte (pas de doublons)

---

## 🧩 Scénario 9: Cas Limites et Erreurs

**Objectif**: Tester la robustesse

### Test 9.1: Requête vide
```bash
curl "http://127.0.0.1:8000/api/search?q=&limit=10"
```

**Résultat attendu**:
- ✅ Erreur 400
- ✅ Message: "Query parameter 'q' is required"

### Test 9.2: Caractères spéciaux
```bash
curl "http://127.0.0.1:8000/api/search?q=50%25+discount+%26+special&limit=5"
```

**Résultat attendu**:
- ✅ Pas de crash
- ✅ Caractères échappés correctement
- ✅ Résultats pertinents

### Test 9.3: Requête très longue
```bash
curl "http://127.0.0.1:8000/api/search?q=$(python3 -c 'print(\"action \"*100)')&limit=5"
```

**Résultat attendu**:
- ✅ Pas de crash
- ✅ Résultats retournés
- ✅ Pas de timeout

### Test 9.4: Mots non trouvés
```bash
curl "http://127.0.0.1:8000/api/search?q=xyzabc123notfound&limit=10"
```

**Résultat attendu**:
- ✅ Retourne tableau vide ou résultats peu pertinents
- ✅ Pas d'erreur
- ✅ `hits: 0` ou scores très bas

---

## 🔍 Scénario 10: Déduplication

**Objectif**: Vérifier que les doublons sont éliminés

### Test 10.1: Recherche large
```bash
curl "http://127.0.0.1:8000/api/search?q=adventure&limit=50" | jq '.results | map(.id) | group_by(.) | map(length) | max'
```

**Résultat attendu**:
- ✅ Résultat = 1 (aucun movie_id n'apparaît plus d'une fois)
- ✅ Déduplication correcte par movie_id

---

## 🎨 Scénario 11: Interface Utilisateur

**Objectif**: Tester l'interface web

### Test 11.1: Page d'accueil
```
Ouvrir: http://127.0.0.1:8000
```

**Résultat attendu**:
- ✅ Page de recherche s'affiche
- ✅ Champ de recherche fonctionnel
- ✅ Design responsive

### Test 11.2: Recherche interactive
```
1. Taper "star wars" dans le champ
2. Appuyer sur Entrée ou cliquer sur Rechercher
```

**Résultat attendu**:
- ✅ Résultats affichés instantanément
- ✅ Scores visibles
- ✅ Posters des films affichés (si disponibles)
- ✅ Temps de recherche affiché

---

## 🛠️ Scénario 12: Health Check

**Objectif**: Vérifier l'état de l'application

### Test 12.1: Health endpoint
```bash
curl "http://127.0.0.1:8000/api/health"
```

**Résultat attendu**:
```json
{
  "status": "ok",
  "symfony_ai": {
    "store": "HybridStore (Postgres)",
    "vectorizer": "Ollama (nomic-embed-text)",
    "search_method": "RRF (Reciprocal Rank Fusion)",
    "powered_by": "Symfony AI Platform"
  }
}
```

---

## 📈 Scénario 13: Analyse des Scores (Score Breakdown)

**Objectif**: Comprendre comment les scores sont calculés

### Test 13.1: Score breakdown détaillé
```bash
curl "http://127.0.0.1:8000/api/search?q=inception&limit=1" | jq '.results[0].score_breakdown'
```

**Résultat attendu**:
```json
{
  "vector_rank": 1,
  "fts_rank": 1,
  "vector_distance": 0.35,
  "fts_score": 25.4,
  "vector_contribution": 0.08,
  "fts_contribution": 0.92,
  "fuzzy_rank": null,
  "fuzzy_similarity": null
}
```

**Validation**:
- ✅ `vector_contribution` ~0.1 (10%)
- ✅ `fts_contribution` ~0.9 (90%)
- ✅ Confirme semantic_ratio=0.1

---

## ✅ Checklist de Validation Globale

### Configuration
- [x] Semantic ratio = 0.1
- [x] BM25 activé (text_search_strategy: bm25)
- [x] RRF k = 10
- [x] Scores normalisés (0-100)
- [x] Default min score = 0
- [x] Fuzzy matching configuré
- [x] Searchable attributes avec boosts

### Fonctionnalités
- [x] Recherche vectorielle fonctionne
- [x] Recherche BM25 fonctionne
- [x] Recherche hybride (RRF) fonctionne
- [x] Fuzzy matching fonctionne
- [x] Pondération des champs fonctionne
- [x] Normalisation des scores fonctionne
- [x] Filtrage par minScore fonctionne
- [x] Déduplication fonctionne
- [x] Score breakdown disponible

### Performance
- [x] Temps de réponse < 200ms
- [x] Support de requêtes concurrentes
- [x] Gestion de limit élevé

### Robustesse
- [x] Gestion des erreurs
- [x] Validation des paramètres
- [x] Pas de crash sur cas limites

---

## 🎯 Tests Recommandés Par Priorité

### Priorité 1 (Critique) ⭐⭐⭐
1. Scénario 3.1: Recherche hybride basique
2. Scénario 1.1: Recherche vectorielle
3. Scénario 2.1: Recherche BM25
4. Scénario 12.1: Health check

### Priorité 2 (Important) ⭐⭐
5. Scénario 4: Pondération des champs
6. Scénario 5: Semantic ratio
7. Scénario 7: Normalisation des scores
8. Scénario 10: Déduplication

### Priorité 3 (Nice to have) ⭐
9. Scénario 3.2: Fuzzy matching
10. Scénario 8: Performance
11. Scénario 9: Cas limites
12. Scénario 13: Score breakdown

---

## 📝 Notes

- Ollama doit être démarré: `ollama serve`
- PostgreSQL doit être accessible
- Le model `nomic-embed-text` doit être téléchargé: `ollama pull nomic-embed-text`
- 1000 films sont indexés dans la base

## 🐛 En Cas de Problème

### Problème: Aucun résultat
```bash
# Vérifier que Ollama fonctionne
curl http://127.0.0.1:11434/api/tags

# Vérifier la base de données
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "SELECT COUNT(*) FROM movies;"

# Vérifier les fonctions BM25
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "SELECT proname FROM pg_proc WHERE proname LIKE '%bm25%';"
```

### Problème: Erreur de vectorisation
```bash
# Vérifier les logs
tail -f /tmp/hybrid_debug.log

# Relancer Ollama
ollama serve
```

### Problème: Erreur SQL
```bash
# Vérifier les logs Symfony
tail -f var/log/dev.log

# Vérifier les extensions PostgreSQL
docker exec postgres_hybrid_search psql -U postgres -d hybrid_search -c "\dx"
```
