<?php

namespace App\Repository;

use App\Entity\Movie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Movie>
 */
class MovieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Movie::class);
    }

    /**
     * Hybrid search combining keyword search and vector similarity
     *
     * @param string $query The search query
     * @param array|null $embedding The query embedding for semantic search
     * @param float $keywordWeight Weight for keyword search (0-1)
     * @param float $semanticWeight Weight for semantic search (0-1)
     * @param int $limit Maximum number of results
     * @return array
     */
    public function hybridSearch(
        string $query,
        ?array $embedding = null,
        float $keywordWeight = 0.5,
        float $semanticWeight = 0.5,
        int $limit = 20
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        // If no embedding provided, do keyword-only search
        if ($embedding === null) {
            return $this->keywordSearch($query, $limit);
        }

        $embeddingString = '[' . implode(',', $embedding) . ']';

        // Hybrid search query combining both approaches
        $sql = "
            WITH keyword_search AS (
                SELECT
                    id,
                    title,
                    overview,
                    genres,
                    poster,
                    release_date,
                    embedding,
                    ts_rank(
                        to_tsvector('english', COALESCE(title, '') || ' ' || COALESCE(overview, '')),
                        plainto_tsquery('english', :query)
                    ) as keyword_score
                FROM movies
                WHERE to_tsvector('english', COALESCE(title, '') || ' ' || COALESCE(overview, ''))
                    @@ plainto_tsquery('english', :query)
            ),
            vector_search AS (
                SELECT
                    id,
                    title,
                    overview,
                    genres,
                    poster,
                    release_date,
                    embedding,
                    1 - (embedding <=> :embedding::vector) as similarity_score
                FROM movies
                WHERE embedding IS NOT NULL
                ORDER BY embedding <=> :embedding::vector
                LIMIT 100
            ),
            combined AS (
                SELECT
                    COALESCE(k.id, v.id) as id,
                    COALESCE(k.title, v.title) as title,
                    COALESCE(k.overview, v.overview) as overview,
                    COALESCE(k.genres, v.genres) as genres,
                    COALESCE(k.poster, v.poster) as poster,
                    COALESCE(k.release_date, v.release_date) as release_date,
                    COALESCE(k.keyword_score, 0) * :keywordWeight as weighted_keyword_score,
                    COALESCE(v.similarity_score, 0) * :semanticWeight as weighted_semantic_score
                FROM keyword_search k
                FULL OUTER JOIN vector_search v ON k.id = v.id
            )
            SELECT
                id,
                title,
                overview,
                genres,
                poster,
                release_date,
                weighted_keyword_score,
                weighted_semantic_score,
                (weighted_keyword_score + weighted_semantic_score) as total_score
            FROM combined
            ORDER BY total_score DESC
            LIMIT :limit
        ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'query' => $query,
            'embedding' => $embeddingString,
            'keywordWeight' => $keywordWeight,
            'semanticWeight' => $semanticWeight,
            'limit' => $limit,
        ]);

        return $result->fetchAllAssociative();
    }

    /**
     * Keyword-only search using PostgreSQL full-text search
     */
    public function keywordSearch(string $query, int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT
                id,
                title,
                overview,
                genres,
                poster,
                release_date,
                ts_rank(
                    to_tsvector('english', COALESCE(title, '') || ' ' || COALESCE(overview, '')),
                    plainto_tsquery('english', :query)
                ) as score
            FROM movies
            WHERE to_tsvector('english', COALESCE(title, '') || ' ' || COALESCE(overview, ''))
                @@ plainto_tsquery('english', :query)
            ORDER BY score DESC
            LIMIT :limit
        ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'query' => $query,
            'limit' => $limit,
        ]);

        return $result->fetchAllAssociative();
    }

    /**
     * Vector similarity search only
     */
    public function vectorSearch(array $embedding, int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $embeddingString = '[' . implode(',', $embedding) . ']';

        $sql = "
            SELECT
                id,
                title,
                overview,
                genres,
                poster,
                release_date,
                1 - (embedding <=> :embedding::vector) as similarity
            FROM movies
            WHERE embedding IS NOT NULL
            ORDER BY embedding <=> :embedding::vector
            LIMIT :limit
        ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'embedding' => $embeddingString,
            'limit' => $limit,
        ]);

        return $result->fetchAllAssociative();
    }
}
