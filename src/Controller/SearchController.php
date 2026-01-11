<?php

namespace App\Controller;

use App\Service\MovieSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private MovieSearchService $movieSearchService
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('search/index.html.twig');
    }

    #[Route('/api/search', name: 'api_search', methods: ['GET', 'POST'])]
    public function search(Request $request): JsonResponse
    {
        // Parse request data - supports both JSON body (POST) and query params (GET)
        $data = [];
        if ($request->getMethod() === 'POST' && $request->getContentTypeFormat() === 'json') {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $query = $data['q'] ?? $request->get('q', '');
        $limit = (int) ($data['limit'] ?? $request->get('limit', 20));
        $boostFields = $data['boost'] ?? $request->get('boost', []);

        if (empty($query)) {
            return $this->json([
                'error' => 'Query parameter "q" is required',
            ], 400);
        }

        $startTime = microtime(true);

        try {
            $results = $this->movieSearchService->search($query, $limit, $boostFields);
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->json([
                'query' => $query,
                'method' => 'Symfony AI Hybrid Search (RRF)',
                'hits' => count($results),
                'processingTimeMs' => $processingTime,
                'results' => $results,
                'boost_applied' => !empty($boostFields) ? $boostFields : null,
                'info' => [
                    'algorithm' => 'Reciprocal Rank Fusion',
                    'components' => [
                        'semantic' => 'pgvector cosine similarity',
                        'fulltext' => 'PostgreSQL ts_rank',
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'symfony_ai' => [
                'store' => 'HybridStore (Postgres)',
                'vectorizer' => 'Ollama (nomic-embed-text)',
                'search_method' => 'RRF (Reciprocal Rank Fusion)',
                'powered_by' => 'Symfony AI Platform',
            ],
        ]);
    }

}
