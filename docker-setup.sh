#!/bin/bash

echo "=== Hybrid Search Setup avec Ollama optimisé ==="
echo ""

# 1. Démarrer les services
echo "🚀 Démarrage PostgreSQL + Ollama..."
docker compose up -d

# 2. Attendre que les services soient prêts
echo "⏳ Attente des services..."
sleep 5

# Vérifier PostgreSQL
until docker exec postgres_hybrid_search pg_isready -U postgres > /dev/null 2>&1; do
  echo "   Attente PostgreSQL..."
  sleep 2
done
echo "✅ PostgreSQL ready"

# Vérifier Ollama
until curl -s http://localhost:11434/api/tags > /dev/null 2>&1; do
  echo "   Attente Ollama..."
  sleep 2
done
echo "✅ Ollama ready"

# 3. Télécharger le modèle d'embeddings (si pas déjà présent)
echo ""
echo "📥 Téléchargement du modèle nomic-embed-text..."
docker exec ollama_embeddings ollama pull nomic-embed-text

# 4. Vérifier que le modèle est bien chargé
echo ""
echo "✅ Vérification du modèle..."
docker exec ollama_embeddings ollama list

# 5. Afficher les stats de ressources
echo ""
echo "📊 Configuration Ollama:"
docker exec ollama_embeddings env | grep OLLAMA

echo ""
echo "🎉 Setup terminé !"
echo ""
echo "Prochaines étapes:"
echo "  1. Import rapide (1000 films avec Shrek):"
echo "     php bin/console app:import-movies --reset --limit=1000"
echo ""
echo "  2. Test recherche conceptuelle:"
echo "     curl 'http://localhost:8000/api/search?q=green+ogre+swamp&limit=3'"
echo ""
echo "  3. Monitoring Ollama:"
echo "     docker logs -f ollama_embeddings"
echo ""
echo "  4. Stats ressources:"
echo "     docker stats ollama_embeddings postgres_hybrid_search"
