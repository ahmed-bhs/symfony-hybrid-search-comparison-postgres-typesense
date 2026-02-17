<?php

namespace App\Controller;

use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'ai.store.postgres.movies')]
        private StoreInterface $store,
        private Vectorizer $vectorizer,
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('search/index.html.twig');
    }

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 20);
        $semanticRatio = (float) $request->query->get('ratio', 0.3);
        $semanticRatio = max(0.0, min(1.0, $semanticRatio));

        if (empty($query)) {
            return $this->json(['error' => 'Query parameter "q" is required'], 400);
        }

        $startTime = microtime(true);

        try {
            $queryVector = $this->vectorizer->vectorize($query);

            $results = iterator_to_array($this->store->query(
                new HybridQuery($queryVector, $query, $semanticRatio),
                ['limit' => $limit, 'includeScoreBreakdown' => true],
            ));

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->json([
                'query' => $query,
                'semanticRatio' => $semanticRatio,
                'hits' => count($results),
                'processingTimeMs' => $processingTime,
                'results' => array_map(fn ($doc) => [
                    'title' => $doc->getMetadata()['title'] ?? 'Unknown',
                    'overview' => $doc->getMetadata()['overview'] ?? '',
                    'genres' => $doc->getMetadata()['genres'] ?? [],
                    'poster' => $doc->getMetadata()['poster'] ?? null,
                    'score' => $doc->getScore(),
                    'score_breakdown' => $doc->getMetadata()['_score_breakdown'] ?? null,
                ], $results),
            ]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $status = 500;

            if ($e instanceof \PDOException || str_contains($message, 'Connection refused')) {
                $message = 'Database connection failed. Is PostgreSQL running?';
                $status = 503;
            }

            return new JsonResponse(json_encode(['error' => $message]), $status, [], true);
        }
    }
}
