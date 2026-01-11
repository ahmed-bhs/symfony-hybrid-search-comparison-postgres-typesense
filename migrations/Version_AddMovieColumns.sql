-- Migration: Ajouter les colonnes réelles pour Movie
-- Objectif: Passer de metadata JSONB à des colonnes structurées

-- 1. Ajouter les nouvelles colonnes
ALTER TABLE movies ADD COLUMN IF NOT EXISTS tmdb_id INTEGER;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS title VARCHAR(255);
ALTER TABLE movies ADD COLUMN IF NOT EXISTS overview TEXT;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS genres JSONB;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS poster VARCHAR(500);
ALTER TABLE movies ADD COLUMN IF NOT EXISTS release_date INTEGER;

-- 2. Migrer les données depuis metadata JSONB
UPDATE movies SET
    tmdb_id = (metadata->>'movie_id')::integer,
    title = metadata->>'title',
    overview = metadata->>'overview',
    genres = metadata->'genres',
    poster = metadata->>'poster',
    release_date = (metadata->>'release_date')::integer
WHERE metadata IS NOT NULL;

-- 3. Créer l'index sur title
CREATE INDEX IF NOT EXISTS idx_title ON movies(title);

-- 4. Afficher le résultat
SELECT
    COUNT(*) as total_movies,
    COUNT(tmdb_id) as with_tmdb_id,
    COUNT(title) as with_title,
    COUNT(overview) as with_overview
FROM movies;
