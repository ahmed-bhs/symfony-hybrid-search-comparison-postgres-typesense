-- Enable pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;

-- Create a function for cosine similarity search
CREATE OR REPLACE FUNCTION cosine_similarity(a vector, b vector)
RETURNS float
LANGUAGE plpgsql
IMMUTABLE
AS $$
BEGIN
    RETURN 1 - (a <=> b);
END;
$$;
