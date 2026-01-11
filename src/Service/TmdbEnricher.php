<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour enrichir les métadonnées de films via l'API TMDb
 */
class TmdbEnricher
{
    private const API_BASE_URL = 'https://api.themoviedb.org/3';
    private const RATE_LIMIT_DELAY = 50000; // 50ms = 20 req/sec (safe limit)

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private ?string $apiKey = null,
        private bool $enabled = false
    ) {
    }

    /**
     * Enrichir les métadonnées d'un film
     *
     * @param int $tmdbId ID du film sur TMDb
     * @return array{keywords: string[], characters: string[], director: ?string}
     */
    public function enrichMovie(int $tmdbId): array
    {
        if (!$this->enabled || !$this->apiKey) {
            return [
                'keywords' => [],
                'characters' => [],
                'director' => null,
            ];
        }

        try {
            // Respecter le rate limit
            usleep(self::RATE_LIMIT_DELAY);

            // Fetcher les détails du film avec keywords et credits
            $response = $this->httpClient->request('GET', self::API_BASE_URL . "/movie/{$tmdbId}", [
                'query' => [
                    'api_key' => $this->apiKey,
                    'append_to_response' => 'keywords,credits',
                    'language' => 'en-US', // Utiliser anglais pour consistance
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning("TMDb API returned {$response->getStatusCode()} for movie {$tmdbId}");
                return $this->getEmptyEnrichment();
            }

            $data = $response->toArray();

            return [
                'keywords' => $this->extractKeywords($data),
                'characters' => $this->extractCharacters($data),
                'director' => $this->extractDirector($data),
            ];

        } catch (\Exception $e) {
            $this->logger->error("Failed to enrich movie {$tmdbId}: {$e->getMessage()}");
            return $this->getEmptyEnrichment();
        }
    }

    /**
     * Extraire les keywords du film
     */
    private function extractKeywords(array $data): array
    {
        if (!isset($data['keywords']['keywords'])) {
            return [];
        }

        $keywords = [];
        foreach ($data['keywords']['keywords'] as $keyword) {
            if (isset($keyword['name'])) {
                $keywords[] = $keyword['name'];
            }
        }

        // Limiter à 15 keywords les plus pertinents
        return array_slice($keywords, 0, 15);
    }

    /**
     * Extraire les personnages principaux (acteur + nom personnage)
     */
    private function extractCharacters(array $data): array
    {
        if (!isset($data['credits']['cast'])) {
            return [];
        }

        $characters = [];
        $cast = array_slice($data['credits']['cast'], 0, 8); // Top 8 acteurs

        foreach ($cast as $actor) {
            // Ajouter le nom de l'acteur
            if (isset($actor['name']) && !empty($actor['name'])) {
                $characters[] = $actor['name'];
            }

            // Ajouter le nom du personnage
            if (isset($actor['character']) && !empty($actor['character'])) {
                // Nettoyer le nom du personnage (enlever "(voice)", etc.)
                $character = preg_replace('/\s*\([^)]*\)\s*/', '', $actor['character']);
                $character = trim($character);

                if (!empty($character) && strlen($character) > 1) {
                    $characters[] = $character;
                }
            }
        }

        return array_unique($characters);
    }

    /**
     * Extraire le réalisateur
     */
    private function extractDirector(array $data): ?string
    {
        if (!isset($data['credits']['crew'])) {
            return null;
        }

        foreach ($data['credits']['crew'] as $member) {
            if (isset($member['job']) && $member['job'] === 'Director') {
                return $member['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * Retourner un enrichissement vide
     */
    private function getEmptyEnrichment(): array
    {
        return [
            'keywords' => [],
            'characters' => [],
            'director' => null,
        ];
    }

    /**
     * Vérifier si le service est activé
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }
}
