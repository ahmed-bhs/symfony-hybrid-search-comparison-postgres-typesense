<?php

namespace App\Service;

use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;
use Psr\Log\LoggerInterface;

class MovieSearchService
{
    public function __construct(
        private StoreInterface $store,
        private Vectorizer $vectorizer,
        private LoggerInterface $logger,
        private TmdbEnricher $tmdbEnricher
    ) {
    }

    public function addMovie(int $id, string $title, string $overview, array $genres = [], ?string $poster = null, ?int $releaseDate = null): void
    {
        $enrichment = $this->tmdbEnricher->enrichMovie($id);

        $content = $this->buildSearchableContent(
            $title,
            $overview,
            $genres,
            $releaseDate,
            $enrichment['keywords'],
            $enrichment['characters'],
            $enrichment['director']
        );

        $metadata = new Metadata();
        $metadata->setText($content);
        $metadata['title'] = $title;
        $metadata['overview'] = $overview;
        $metadata['genres'] = $genres;
        $metadata['poster'] = $poster;
        $metadata['release_date'] = $releaseDate;
        $metadata['movie_id'] = $id;

        try {
            $vector = $this->vectorizer->vectorize($content);

            $document = new VectorDocument(
                id: Uuid::v7(),
                vector: $vector,
                metadata: $metadata
            );

            $this->store->add($document);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add movie to store', [
                'movie_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function buildSearchableContent(
        string $title,
        string $overview,
        array $genres,
        ?int $releaseDate,
        array $keywords = [],
        array $characters = [],
        ?string $director = null
    ): string {
        $fields = [];

        $fields[] = "title: {$title}";

        if ($releaseDate) {
            $year = date('Y', $releaseDate);
            $fields[] = "year: {$year}";
        }

        if (!empty($genres)) {
            $genreList = implode(', ', $genres);
            $fields[] = "genres: {$genreList}";
        }

        if (!empty($overview)) {
            $fields[] = "overview: {$overview}";
        }

        if (!empty($keywords)) {
            $keywordList = implode(', ', $keywords);
            $fields[] = "keywords: {$keywordList}";
        }

        if (!empty($characters)) {
            $characterList = implode(', ', $characters);
            $fields[] = "characters: {$characterList}";
        }

        if ($director) {
            $fields[] = "director: {$director}";
        }

        return implode("\n", $fields);
    }

    public function search(string $query, int $limit = 20, array $boostFields = []): array
    {
        try {
            $queryVector = $this->vectorizer->vectorize($query);

            $options = [
                'limit' => $limit,
                'q' => $query,
                'includeScoreBreakdown' => true
            ];

            if (!empty($boostFields)) {
                $options['boostFields'] = $boostFields;
            }

            $results = $this->store->query($queryVector, $options);

            $seen = [];
            $deduplicated = [];

            foreach ($results as $document) {
                $metadata = $document->metadata;
                $movieId = $metadata['movie_id'] ?? null;

                if ($movieId && isset($seen[$movieId])) {
                    continue;
                }

                $result = [
                    'id' => $movieId,
                    'title' => $metadata['title'] ?? '',
                    'overview' => $metadata['overview'] ?? '',
                    'genres' => $metadata['genres'] ?? [],
                    'poster' => $metadata['poster'] ?? null,
                    'release_date' => $metadata['release_date'] ?? null,
                    'score' => $document->score,
                ];

                if (isset($metadata['_score_breakdown'])) {
                    $result['score_breakdown'] = $metadata['_score_breakdown'];
                }

                if (isset($metadata['_applied_boosts'])) {
                    $result['applied_boosts'] = $metadata['_applied_boosts'];
                }

                if ($movieId) {
                    $seen[$movieId] = true;
                }
                $deduplicated[] = $result;
            }

            return $deduplicated;
        } catch (\Exception $e) {
            $this->logger->error('Search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function setup(int $vectorSize = 768): void
    {
        $this->store->setup(['vector_size' => $vectorSize]);
    }

    public function drop(): void
    {
        $this->store->drop();
    }
}
