<?php

namespace App\Controller;

use App\Service\MovieSearchService;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Vectorizer;
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
        private MovieSearchService $movieSearchService,
        #[Autowire(service: 'ai.store.postgres.movies')]
        private StoreInterface $bm25Store,
        #[Autowire(service: 'ai.store.postgres.movies_native')]
        private StoreInterface $nativeStore,
        private Vectorizer $vectorizer,
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('search/index.html.twig');
    }

    /**
     * Search using BM25 text search strategy (requires plpgsql_bm25 extension).
     */
    #[Route('/api/search/bm25', name: 'api_search_bm25', methods: ['GET'])]
    public function searchBm25(Request $request): JsonResponse
    {
        return $this->performSearch(
            $request,
            $this->bm25Store,
            'BM25 (plpgsql_bm25 extension)'
        );
    }

    /**
     * Search using native PostgreSQL FTS (no extension required).
     */
    #[Route('/api/search/native', name: 'api_search_native', methods: ['GET'])]
    public function searchNative(Request $request): JsonResponse
    {
        return $this->performSearch(
            $request,
            $this->nativeStore,
            'Native PostgreSQL FTS (ts_rank_cd)'
        );
    }

    /**
     * Default search endpoint (uses BM25).
     */
    #[Route('/api/search', name: 'api_search', methods: ['GET', 'POST'])]
    public function search(Request $request): JsonResponse
    {
        return $this->searchBm25($request);
    }

    private function performSearch(Request $request, StoreInterface $store, string $strategyName): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 20);

        if (empty($query)) {
            return $this->json(['error' => 'Query parameter "q" is required'], 400);
        }

        $startTime = microtime(true);

        try {
            $queryVector = $this->vectorizer->vectorize($query);

            $results = $store->query($queryVector, [
                'limit' => $limit,
                'q' => $query,
                'includeScoreBreakdown' => true,
            ]);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->json([
                'query' => $query,
                'strategy' => $strategyName,
                'hits' => count($results),
                'processingTimeMs' => $processingTime,
                'results' => array_map(fn ($doc) => [
                    'title' => $doc->metadata['title'] ?? 'Unknown',
                    'overview' => $doc->metadata['overview'] ?? '',
                    'genres' => $doc->metadata['genres'] ?? [],
                    'poster' => $doc->metadata['poster'] ?? null,
                    'score' => $doc->score,
                    'score_breakdown' => $doc->metadata['_score_breakdown'] ?? null,
                ], $results),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Search failed: '.$e->getMessage(),
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

    /**
     * Compare BM25 vs Native PostgreSQL FTS results.
     */
    #[Route('/api/compare', name: 'api_compare', methods: ['GET'])]
    public function compare(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 5);

        if (empty($query)) {
            return $this->json(['error' => 'Query parameter "q" is required'], 400);
        }

        $queryVector = $this->vectorizer->vectorize($query);

        $options = [
            'limit' => $limit,
            'q' => $query,
            'includeScoreBreakdown' => true,
        ];

        // Search with BM25
        $bm25Start = microtime(true);
        $bm25Results = $this->bm25Store->query($queryVector, $options);
        $bm25Time = round((microtime(true) - $bm25Start) * 1000, 2);

        // Search with Native FTS
        $nativeStart = microtime(true);
        try {
            $nativeResults = $this->nativeStore->query($queryVector, $options);
            $nativeTime = round((microtime(true) - $nativeStart) * 1000, 2);
            $nativeError = null;
        } catch (\Exception $e) {
            $nativeResults = [];
            $nativeTime = 0;
            $nativeError = $e->getMessage();
        }

        return $this->json([
            'query' => $query,
            'bm25' => [
                'strategy' => 'BM25 (plpgsql_bm25 extension)',
                'processingTimeMs' => $bm25Time,
                'hits' => count($bm25Results),
                'results' => array_map(fn ($doc) => [
                    'title' => $doc->metadata['title'] ?? 'Unknown',
                    'score' => $doc->score,
                ], $bm25Results),
            ],
            'native' => [
                'strategy' => 'Native PostgreSQL FTS (ts_rank_cd)',
                'processingTimeMs' => $nativeTime,
                'hits' => count($nativeResults),
                'error' => $nativeError,
                'results' => array_map(fn ($doc) => [
                    'title' => $doc->metadata['title'] ?? 'Unknown',
                    'score' => $doc->score,
                ], $nativeResults),
            ],
        ]);
    }
}
