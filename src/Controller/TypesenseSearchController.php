<?php

namespace App\Controller;

use ACSEO\TypesenseBundle\Client\TypesenseClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TypesenseSearchController extends AbstractController
{
    private TypesenseClient $typesenseClient;

    public function __construct(TypesenseClient $typesenseClient)
    {
        $this->typesenseClient = $typesenseClient;
    }

    #[Route('/typesense', name: 'typesense_index')]
    public function index(): Response
    {
        return $this->render('typesense/index.html.twig');
    }

    private function generateEmbedding(string $text): ?array
    {
        try {
            $response = file_get_contents('http://localhost:11434/api/embeddings', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode([
                        'model' => 'nomic-embed-text',
                        'prompt' => $text,
                    ]),
                    'timeout' => 10,
                ]
            ]));

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            return $data['embedding'] ?? null;

        } catch (\Exception $e) {
            return null;
        }
    }

    #[Route('/typesense/search', name: 'typesense_search', methods: ['GET', 'POST'])]
    public function search(Request $request): JsonResponse
    {
        // Parse request data - supports both JSON body (POST) and query params (GET)
        $data = [];
        if ($request->getMethod() === 'POST' && $request->getContentTypeFormat() === 'json') {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $query = $data['q'] ?? $request->get('q', '');
        $limit = (int) ($data['limit'] ?? $request->get('limit', 20));

        // Facet filters from request
        $genreFilter = $data['genre'] ?? $request->get('genre');
        $yearFilter = $data['year'] ?? $request->get('year');

        // Hybrid search ratio (0.0 to 1.0, default 0.5 for balanced search)
        $hybridRatio = (float) ($data['hybrid_ratio'] ?? $request->get('hybrid_ratio', 0.5));

        if (empty($query)) {
            return $this->json([
                'error' => 'Query parameter "q" is required',
            ], 400);
        }

        $startTime = microtime(true);

        try {
            // Build search parameters
            $searchParams = [
                'q' => $query,
                'query_by' => 'title,overview',
                'per_page' => $limit,

                // Enable hybrid search with BM25 and semantic
                'use_cache' => false,
                'exhaustive_search' => false,

                // Hybrid search parameters
                'prefix' => true,
                'infix' => 'fallback',

                // Text match type using BM25
                'text_match_type' => 'max_weight',

                // Facets configuration
                'facet_by' => 'genres,release_date',
                'max_facet_values' => 50,

                // Highlighting
                'highlight_fields' => 'title,overview',
                'highlight_full_fields' => 'title,overview',
            ];

            // Add filter by if facets are provided
            $filterBy = [];
            if ($genreFilter) {
                $filterBy[] = sprintf('genres:=[%s]', $genreFilter);
            }
            if ($yearFilter) {
                $filterBy[] = sprintf('release_date:=%d', (int)$yearFilter);
            }
            if (!empty($filterBy)) {
                $searchParams['filter_by'] = implode(' && ', $filterBy);
            }

            // Enable semantic/hybrid search with embeddings
            // Hybrid ratio: 0.0 = full keyword/BM25, 1.0 = full semantic
            if ($hybridRatio > 0) {
                // Generate embedding for the search query using Ollama
                $queryEmbedding = $this->generateEmbedding($query);

                if ($queryEmbedding) {
                    $searchParams['vector_query'] = sprintf(
                        'embedding:([%s], k: %d, alpha: %f)',
                        implode(',', $queryEmbedding),
                        $limit,
                        $hybridRatio
                    );
                }
            }

            // Execute search
            $client = $this->typesenseClient;

            if ($hybridRatio > 0 && isset($searchParams['vector_query'])) {
                $multiSearchParams = [
                    'searches' => [
                        array_merge(['collection' => 'movies'], $searchParams)
                    ]
                ];

                $multiResults = $client->multiSearch->perform($multiSearchParams, []);
                $results = $multiResults['results'][0] ?? [];
            } else {
                $results = $client->collections['movies']->documents->search($searchParams);
            }

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->json([
                'query' => $query,
                'method' => 'Typesense Hybrid Search (BM25 + Semantic)',
                'hits' => $results['found'] ?? 0,
                'processingTimeMs' => $processingTime,
                'results' => array_map(function($hit) {
                    return [
                        'id' => $hit['document']['id'] ?? null,
                        'tmdb_id' => $hit['document']['tmdb_id'] ?? null,
                        'title' => $hit['document']['title'] ?? null,
                        'overview' => $hit['document']['overview'] ?? null,
                        'genres' => $hit['document']['genres'] ?? [],
                        'poster' => $hit['document']['poster'] ?? null,
                        'release_date' => $hit['document']['release_date'] ?? null,
                        'score' => $hit['text_match'] ?? 0,
                        'highlights' => $hit['highlights'] ?? [],
                    ];
                }, $results['hits'] ?? []),
                'facets' => $results['facet_counts'] ?? [],
                'config' => [
                    'hybrid_ratio' => $hybridRatio,
                    'bm25_weight' => round(1 - $hybridRatio, 2),
                    'semantic_weight' => $hybridRatio,
                    'filters' => [
                        'genre' => $genreFilter,
                        'year' => $yearFilter,
                    ],
                ],
                'info' => [
                    'algorithm' => 'Typesense Hybrid Search (BM25 + Semantic)',
                    'components' => [
                        'keyword' => 'BM25 (Best Match 25)',
                        'semantic' => 'Vector similarity (Ollama nomic-embed-text)',
                        'search_type' => $hybridRatio == 0 ? 'keyword_only' : ($hybridRatio == 1 ? 'semantic_only' : 'hybrid'),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Search failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    #[Route('/typesense/health', name: 'typesense_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        try {
            $client = $this->typesenseClient;
            $health = $client->health->retrieve();

            return $this->json([
                'status' => 'ok',
                'typesense' => $health,
                'collection' => 'movies',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/typesense/conversation', name: 'typesense_conversation', methods: ['POST'])]
    public function conversation(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $question = $data['question'] ?? '';

            if (empty($question)) {
                return $this->json(['error' => 'Question is required'], 400);
            }

            // Step 1: Search for relevant movies using Typesense with semantic search
            $queryEmbedding = $this->generateEmbedding($question);

            if (!$queryEmbedding) {
                throw new \Exception('Failed to generate embedding for the question');
            }

            $searchParams = [
                'q' => $question,
                'query_by' => 'title,overview',
                'per_page' => 5,
                'vector_query' => sprintf(
                    'embedding:([%s], k: %d, alpha: %f)',
                    implode(',', $queryEmbedding),
                    10,
                    0.7
                )
            ];

            $multiSearchParams = [
                'searches' => [
                    array_merge(['collection' => 'movies'], $searchParams)
                ]
            ];

            $multiResults = $this->typesenseClient->multiSearch->perform($multiSearchParams, []);
            $searchResults = $multiResults['results'][0] ?? [];

            $movies = [];
            $sources = [];
            foreach ($searchResults['hits'] as $hit) {
                $doc = $hit['document'];
                $movies[] = [
                    'title' => $doc['title'],
                    'overview' => $doc['overview'] ?? '',
                    'genres' => $doc['genres'] ?? [],
                    'release_date' => isset($doc['release_date']) ? date('Y', $doc['release_date']) : 'Unknown'
                ];
                $sources[] = ['title' => $doc['title']];
            }

            // Step 2: Create context from search results
            $context = "Voici des informations sur les films pertinents:\n\n";
            foreach ($movies as $movie) {
                $genres = implode(', ', $movie['genres']);
                $context .= "- {$movie['title']} ({$movie['release_date']}) - Genres: {$genres}\n";
                $context .= "  Synopsis: {$movie['overview']}\n\n";
            }

            // Step 3: Generate response using Ollama
            $prompt = "Tu es un assistant cinéma expert. Réponds à la question de l'utilisateur en te basant UNIQUEMENT sur les films fournis ci-dessous. Sois précis et cite les titres des films mentionnés.\n\n";
            $prompt .= $context;
            $prompt .= "\nQuestion: {$question}\n\nRéponse:";

            $response = file_get_contents('http://localhost:11434/api/generate', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode([
                        'model' => 'llama3.2:1b',
                        'prompt' => $prompt,
                        'stream' => false,
                        'options' => [
                            'temperature' => 0.7,
                            'num_predict' => 300,
                        ]
                    ]),
                ],
            ]));

            if ($response === false) {
                throw new \Exception('Failed to call Ollama API');
            }

            $ollamaResponse = json_decode($response, true);
            $answer = $ollamaResponse['response'] ?? 'Désolé, je n\'ai pas pu générer de réponse.';

            return $this->json([
                'answer' => trim($answer),
                'sources' => $sources,
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
