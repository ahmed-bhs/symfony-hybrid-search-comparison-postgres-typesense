<?php

namespace App\Command;

use App\Entity\Movie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Typesense\Client;

#[AsCommand(
    name: 'app:typesense:index-movies',
    description: 'Create Typesense collection and index movies',
)]
class TypesenseIndexMoviesCommand extends Command
{
    private Client $client;

    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();

        $this->client = new Client([
            'nodes' => [
                [
                    'host' => 'localhost',
                    'port' => '8108',
                    'protocol' => 'http',
                ],
            ],
            'api_key' => '123',
            'connection_timeout_seconds' => 5,
        ]);
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
                    'timeout' => 30,
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Typesense Movies Indexation');

        // Step 1: Delete collection if exists
        $io->section('Step 1: Checking existing collection');
        try {
            $this->client->collections['movies']->retrieve();
            $io->warning('Collection "movies" already exists. Deleting...');
            $this->client->collections['movies']->delete();
            $io->success('Collection deleted');
        } catch (\Exception $e) {
            $io->info('Collection does not exist yet');
        }

        // Step 2: Create collection with vector field
        $io->section('Step 2: Creating collection with vector field');
        try {
            $schema = [
                'name' => 'movies',
                'fields' => [
                    ['name' => 'id', 'type' => 'string'],
                    ['name' => 'tmdb_id', 'type' => 'int32', 'optional' => true],
                    ['name' => 'title', 'type' => 'string'],
                    ['name' => 'overview', 'type' => 'string', 'optional' => true],
                    ['name' => 'genres', 'type' => 'string[]', 'facet' => true, 'optional' => true],
                    ['name' => 'poster', 'type' => 'string', 'optional' => true],
                    ['name' => 'release_date', 'type' => 'int32', 'facet' => true, 'optional' => true],
                    // Vector field for semantic search
                    [
                        'name' => 'embedding',
                        'type' => 'float[]',
                        'num_dim' => 768,
                        'optional' => true,
                    ],
                ],
            ];

            $this->client->collections->create($schema);
            $io->success('Collection "movies" created successfully');
            $io->info('Embeddings will be generated via Ollama during indexation');
        } catch (\Exception $e) {
            $io->error('Failed to create collection: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Step 3: Index movies (Typesense will auto-generate embeddings via Ollama)
        $io->section('Step 3: Indexing movies (Ollama will generate embeddings)');

        // Fetch movies from database without embeddings
        $conn = $this->em->getConnection();
        $sql = "SELECT
                    id,
                    metadata->>'title' as title,
                    metadata->>'overview' as overview,
                    metadata->>'genres' as genres,
                    metadata->>'poster' as poster,
                    metadata->>'release_year' as release_year,
                    (metadata->>'tmdb_id')::int as tmdb_id
                FROM movies";

        $result = $conn->executeQuery($sql);
        $movies = $result->fetchAllAssociative();

        $io->info(sprintf('Found %d movies to index', count($movies)));

        $progressBar = $io->createProgressBar(count($movies));
        $progressBar->start();

        $indexed = 0;
        $errors = 0;

        foreach ($movies as $movie) {
            try {
                // Parse genres from JSON string if present
                $genres = [];
                if (!empty($movie['genres'])) {
                    $genresData = json_decode($movie['genres'], true);
                    if (is_array($genresData)) {
                        $genres = $genresData;
                    }
                }

                // Generate embedding via Ollama
                $title = $movie['title'] ?? 'Unknown';
                $overview = $movie['overview'] ?? '';
                $textToEmbed = $title . ' ' . $overview;

                $embedding = $this->generateEmbedding($textToEmbed);

                $document = [
                    'id' => (string) $movie['id'],
                    'tmdb_id' => $movie['tmdb_id'] ?? 0,
                    'title' => $title,
                    'overview' => $overview,
                    'genres' => $genres,
                    'poster' => $movie['poster'] ?? '',
                    'release_date' => (int) ($movie['release_year'] ?? 0),
                ];

                // Add embedding if generated successfully
                if ($embedding && count($embedding) === 768) {
                    $document['embedding'] = $embedding;
                }

                $this->client->collections['movies']->documents->create($document);
                $indexed++;
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 5) {
                    $io->warning(sprintf(
                        'Failed to index movie %s: %s',
                        $movie['title'] ?? 'unknown',
                        $e->getMessage()
                    ));
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->success(sprintf(
            'Indexation complete! Indexed: %d, Errors: %d',
            $indexed,
            $errors
        ));

        return Command::SUCCESS;
    }
}
