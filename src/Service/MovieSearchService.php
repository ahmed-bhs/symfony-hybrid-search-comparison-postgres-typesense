<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

class MovieSearchService
{
    public function __construct(
        #[Autowire(service: 'ai.store.postgres.movies')]
        private StoreInterface $store,
        private Vectorizer $vectorizer,
        private LoggerInterface $logger,
        private TmdbEnricher $tmdbEnricher,
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

    public function setup(int $vectorSize = 768): void
    {
        $this->store->setup(['vector_size' => $vectorSize]);
    }

    public function drop(): void
    {
        $this->store->drop();
    }
}
