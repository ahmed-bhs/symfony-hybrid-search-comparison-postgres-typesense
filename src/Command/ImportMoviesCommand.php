<?php

namespace App\Command;

use App\Service\MovieSearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-movies',
    description: 'Import movies into Symfony AI HybridStore with embeddings',
)]
class ImportMoviesCommand extends Command
{
    public function __construct(
        private MovieSearchService $movieSearchService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to JSON file', '/home/ahmed/meilisearch-datasets/movies.json')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of movies to import', null)
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Reset store before importing')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for progress updates', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        $limit = $input->getOption('limit');
        $reset = $input->getOption('reset');
        $batchSize = (int) $input->getOption('batch-size');

        if (!file_exists($file)) {
            $io->error("File not found: {$file}");
            return Command::FAILURE;
        }

        $io->title('Importing Movies into Symfony AI HybridStore');

        // Setup or reset store
        if ($reset) {
            $io->section('Resetting HybridStore');
            try {
                $this->movieSearchService->drop();
                $io->success('Store dropped');
            } catch (\Exception $e) {
                $io->warning('Could not drop store: ' . $e->getMessage());
            }
        }

        $io->section('Setting up HybridStore');
        try {
            $this->movieSearchService->setup(768); // nomic-embed-text dimension
            $io->success('HybridStore ready with pgvector + full-text search');
        } catch (\Exception $e) {
            $io->error('Failed to setup store: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Load JSON file
        $io->section('Loading JSON file');
        $jsonContent = file_get_contents($file);
        $movies = json_decode($jsonContent, true);

        if (!is_array($movies)) {
            $io->error('Invalid JSON format');
            return Command::FAILURE;
        }

        $total = $limit ? min($limit, count($movies)) : count($movies);
        $io->text("Found {$total} movies to import");

        // Import movies with batch processing for better performance
        $io->section('Importing movies with Symfony AI');
        $progressBar = $io->createProgressBar($total);
        $progressBar->start();

        $count = 0;
        $errors = 0;
        $batchSize = (int) $input->getOption('batch-size');
        $batch = [];

        foreach ($movies as $movieData) {
            if ($limit && $count >= $limit) {
                break;
            }

            $batch[] = $movieData;
            $count++;

            // Process batch when it reaches the batch size or at the end
            if (count($batch) >= $batchSize || $count >= $total || ($limit && $count >= $limit)) {
                foreach ($batch as $movie) {
                    try {
                        $this->movieSearchService->addMovie(
                            id: $movie['id'] ?? $count,
                            title: $movie['title'] ?? 'Unknown',
                            overview: $movie['overview'] ?? '',
                            genres: $movie['genres'] ?? [],
                            poster: $movie['poster'] ?? null,
                            releaseDate: $movie['release_date'] ?? null
                        );
                        $progressBar->advance();
                    } catch (\Exception $e) {
                        $errors++;
                        if ($errors <= 5) {
                            $io->warning("Failed to import movie: " . $e->getMessage());
                        }
                        $progressBar->advance();
                    }
                }
                $batch = [];

                // Force garbage collection to free memory
                gc_collect_cycles();
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        if ($errors > 0) {
            $io->warning("Imported {$count} movies with {$errors} errors");
        } else {
            $io->success("Successfully imported {$count} movies into HybridStore!");
        }

        $io->note([
            'The movies are now searchable using:',
            '- Semantic search (vector embeddings)',
            '- Full-text search (PostgreSQL ts_rank)',
            '- Hybrid search with RRF (Reciprocal Rank Fusion)',
        ]);

        return Command::SUCCESS;
    }
}
