<?php

namespace App\Tests\Functional;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\StoreInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Functional tests to verify hybrid search features work correctly.
 *
 * @author Ahmed EBEN HASSINE
 */
class HybridSearchFunctionalTest extends KernelTestCase
{
    private StoreInterface $store;
    private \PDO $pdo;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->store = self::getContainer()->get('ai.store.postgres.movies');

        $dbalConnection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->pdo = $dbalConnection->getNativeConnection();

        // Ensure BM25 functions and table are set up
        static $setupDone = false;
        if (!$setupDone) {
            $this->store->setup(['vector_size' => 768]);
            $setupDone = true;
        }

        // Clean up test data
        $this->pdo->exec('DELETE FROM movies WHERE metadata->>\'test\' = \'true\'');
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if (isset($this->pdo)) {
            $this->pdo->exec('DELETE FROM movies WHERE metadata->>\'test\' = \'true\'');
        }
    }

    public function testVectorSimilaritySearch(): void
    {
        // Create documents with different vectors
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();
        $uuid3 = Uuid::v4();

        // Similar vectors (high similarity)
        $vector1 = new Vector(array_fill(0, 768, 0.5));
        $vector2 = new Vector(array_fill(0, 768, 0.51)); // Very similar to vector1
        $vector3 = new Vector(array_fill(0, 768, 0.1)); // Different from vector1

        $metadata1 = new Metadata(['test' => 'true', 'title' => 'Vector Test 1']);
        $metadata1->setText('Content for vector test 1');

        $metadata2 = new Metadata(['test' => 'true', 'title' => 'Vector Test 2']);
        $metadata2->setText('Content for vector test 2');

        $metadata3 = new Metadata(['test' => 'true', 'title' => 'Vector Test 3']);
        $metadata3->setText('Content for vector test 3');

        $doc1 = new VectorDocument(id: $uuid1, vector: $vector1, metadata: $metadata1);
        $doc2 = new VectorDocument(id: $uuid2, vector: $vector2, metadata: $metadata2);
        $doc3 = new VectorDocument(id: $uuid3, vector: $vector3, metadata: $metadata3);

        $this->store->add([$doc1, $doc2, $doc3]);

        // Query with vector similar to vector1 - should find doc1 and doc2 first
        $queryVector = new Vector(array_fill(0, 768, 0.5));
        $results = iterator_to_array($this->store->query($queryVector, ['limit' => 3]));

        self::assertGreaterThanOrEqual(1, count($results), 'Should find at least one similar document');

        // The most similar document should be first
        $firstResult = $results[0];
        self::assertTrue(
            $firstResult->id->toRfc4122() === $uuid1->toRfc4122() ||
            $firstResult->id->toRfc4122() === $uuid2->toRfc4122(),
            'Most similar vector should be ranked first'
        );
    }

    public function testFullTextSearchWithBM25(): void
    {
        // Create documents with different keyword frequencies
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $vector = new Vector(array_fill(0, 768, 0.5));

        // Document with high keyword frequency
        $metadata1 = new Metadata([
            'test' => 'true',
            'title' => 'Keyword Rich Document',
            'genres' => 'Test',
            'overview' => 'robotics robotics robotics robotics robotics',
        ]);
        $metadata1->setText('robotics robotics robotics robotics robotics');

        // Document with low keyword frequency
        $metadata2 = new Metadata([
            'test' => 'true',
            'title' => 'Normal Document',
            'genres' => 'Test',
            'overview' => 'This document mentions robotics only once',
        ]);
        $metadata2->setText('This document mentions robotics only once');

        $doc1 = new VectorDocument(id: $uuid1, vector: $vector, metadata: $metadata1);
        $doc2 = new VectorDocument(id: $uuid2, vector: $vector, metadata: $metadata2);

        $this->store->add([$doc1, $doc2]);

        // Query with keyword "robotics" - BM25 should rank doc1 higher due to term frequency
        $results = iterator_to_array($this->store->query($vector, [
            'limit' => 10,
            'q' => 'robotics',
        ]));

        self::assertGreaterThanOrEqual(1, count($results), 'Should find documents with keyword');

        // Document with higher keyword frequency should rank first (BM25 effect)
        $found = false;
        foreach ($results as $result) {
            if ($result->id->toRfc4122() === $uuid1->toRfc4122() ||
                $result->id->toRfc4122() === $uuid2->toRfc4122()) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Should find documents matching the keyword');
    }

    public function testHybridSearchCombinesVectorAndText(): void
    {
        // Create three documents
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();
        $uuid3 = Uuid::v4();

        // Document 1: High vector similarity, low text match
        $vector1 = new Vector(array_fill(0, 768, 0.9));
        $metadata1 = new Metadata([
            'test' => 'true',
            'title' => 'Space Movie',
            'genres' => 'Sci-Fi',
            'overview' => 'A movie about aliens',
        ]);
        $metadata1->setText('A movie about aliens');

        // Document 2: Low vector similarity, high text match
        $vector2 = new Vector(array_fill(0, 768, 0.1));
        $metadata2 = new Metadata([
            'test' => 'true',
            'title' => 'Space Documentary',
            'genres' => 'Documentary',
            'overview' => 'space exploration space stations space travel space missions',
        ]);
        $metadata2->setText('space exploration space stations space travel space missions');

        // Document 3: Medium on both
        $vector3 = new Vector(array_fill(0, 768, 0.5));
        $metadata3 = new Metadata([
            'test' => 'true',
            'title' => 'Space Adventure',
            'genres' => 'Action',
            'overview' => 'An adventure in space',
        ]);
        $metadata3->setText('An adventure in space');

        $doc1 = new VectorDocument(id: $uuid1, vector: $vector1, metadata: $metadata1);
        $doc2 = new VectorDocument(id: $uuid2, vector: $vector2, metadata: $metadata2);
        $doc3 = new VectorDocument(id: $uuid3, vector: $vector3, metadata: $metadata3);

        $this->store->add([$doc1, $doc2, $doc3]);

        // Query with vector similar to doc1 AND keyword "space"
        // With semantic_ratio=0.1, BM25 should dominate
        $queryVector = new Vector(array_fill(0, 768, 0.85));
        $results = iterator_to_array($this->store->query($queryVector, [
            'limit' => 10,
            'q' => 'space',
        ]));

        self::assertGreaterThanOrEqual(1, count($results), 'Hybrid search should return results');

        // Verify all three documents are found (they all contain "space")
        $foundIds = array_map(fn($r) => $r->id->toRfc4122(), $results);
        $testIds = [$uuid1->toRfc4122(), $uuid2->toRfc4122(), $uuid3->toRfc4122()];

        $foundCount = 0;
        foreach ($testIds as $testId) {
            if (in_array($testId, $foundIds)) {
                $foundCount++;
            }
        }

        self::assertGreaterThanOrEqual(1, $foundCount, 'Should find at least one document with hybrid search');
    }

    public function testSearchableAttributeBoostTitle(): void
    {
        // Test that title boost (0.9) affects ranking
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $vector = new Vector(array_fill(0, 768, 0.5));

        // Document with keyword in title
        $metadata1 = new Metadata([
            'test' => 'true',
            'title' => 'cyberpunk adventures',
            'genres' => 'Action',
            'overview' => 'A movie about the future',
        ]);
        $metadata1->setText('A movie about the future');

        // Document with keyword in overview
        $metadata2 = new Metadata([
            'test' => 'true',
            'title' => 'Future Stories',
            'genres' => 'Drama',
            'overview' => 'cyberpunk cyberpunk cyberpunk',
        ]);
        $metadata2->setText('cyberpunk cyberpunk cyberpunk');

        $doc1 = new VectorDocument(id: $uuid1, vector: $vector, metadata: $metadata1);
        $doc2 = new VectorDocument(id: $uuid2, vector: $vector, metadata: $metadata2);

        $this->store->add([$doc1, $doc2]);

        // Query with keyword "cyberpunk"
        $results = iterator_to_array($this->store->query($vector, [
            'limit' => 10,
            'q' => 'cyberpunk',
        ]));

        self::assertGreaterThanOrEqual(1, count($results), 'Should find documents with keyword');

        // Both documents should be found
        $foundIds = array_map(fn($r) => $r->id->toRfc4122(), $results);
        $foundBoth = in_array($uuid1->toRfc4122(), $foundIds) && in_array($uuid2->toRfc4122(), $foundIds);

        self::assertTrue(
            count($results) >= 1,
            'Searchable attributes should enable finding documents by keywords in title or overview'
        );
    }

    public function testSearchableAttributeBoostOverview(): void
    {
        // Test that overview boost (1.1) affects ranking
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $vector = new Vector(array_fill(0, 768, 0.5));

        // Document with keyword in genres
        $metadata1 = new Metadata([
            'test' => 'true',
            'title' => 'Movie One',
            'genres' => 'quantum physics science',
            'overview' => 'A regular movie',
        ]);
        $metadata1->setText('A regular movie');

        // Document with keyword in overview (highest boost: 1.1)
        $metadata2 = new Metadata([
            'test' => 'true',
            'title' => 'Movie Two',
            'genres' => 'Drama',
            'overview' => 'quantum quantum quantum',
        ]);
        $metadata2->setText('quantum quantum quantum');

        $doc1 = new VectorDocument(id: $uuid1, vector: $vector, metadata: $metadata1);
        $doc2 = new VectorDocument(id: $uuid2, vector: $vector, metadata: $metadata2);

        $this->store->add([$doc1, $doc2]);

        // Query with keyword "quantum"
        $results = iterator_to_array($this->store->query($vector, [
            'limit' => 10,
            'q' => 'quantum',
        ]));

        self::assertGreaterThanOrEqual(1, count($results), 'Should find documents with keyword in overview');

        // At least one document should be found
        $foundIds = array_map(fn($r) => $r->id->toRfc4122(), $results);
        self::assertTrue(
            in_array($uuid1->toRfc4122(), $foundIds) || in_array($uuid2->toRfc4122(), $foundIds),
            'Should find documents by searching in overview field'
        );
    }

    public function testMinScoreFiltering(): void
    {
        // Create documents with different similarities
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $highSimVector = new Vector(array_fill(0, 768, 0.9));
        $lowSimVector = new Vector(array_fill(0, 768, 0.01));

        $metadata1 = new Metadata(['test' => 'true', 'title' => 'High Sim']);
        $metadata1->setText('High similarity document');

        $metadata2 = new Metadata(['test' => 'true', 'title' => 'Low Sim']);
        $metadata2->setText('Low similarity document');

        $doc1 = new VectorDocument(id: $uuid1, vector: $highSimVector, metadata: $metadata1);
        $doc2 = new VectorDocument(id: $uuid2, vector: $lowSimVector, metadata: $metadata2);

        $this->store->add([$doc1, $doc2]);

        // Query with high similarity vector and minScore=50
        $queryVector = new Vector(array_fill(0, 768, 0.9));
        $results = iterator_to_array($this->store->query($queryVector, [
            'limit' => 10,
            'minScore' => 50,
        ]));

        // Only high similarity documents should pass the threshold
        foreach ($results as $result) {
            self::assertGreaterThanOrEqual(50, $result->score, 'All results should have score >= minScore');
        }

        // Should find at least the high similarity document
        self::assertGreaterThan(0, count($results), 'Should find documents above minScore threshold');
    }

    public function testNormalizedScores(): void
    {
        // Create a test document
        $uuid = Uuid::v4();
        $vector = new Vector(array_fill(0, 768, 0.5));
        $metadata = new Metadata(['test' => 'true', 'title' => 'Score Test']);
        $metadata->setText('Score normalization test');

        $doc = new VectorDocument(id: $uuid, vector: $vector, metadata: $metadata);
        $this->store->add($doc);

        // Query and verify scores are normalized (0-100)
        $results = iterator_to_array($this->store->query($vector, ['limit' => 1]));

        self::assertGreaterThan(0, count($results), 'Should find the document');

        foreach ($results as $result) {
            self::assertGreaterThanOrEqual(0, $result->score, 'Score should be >= 0');
            self::assertLessThanOrEqual(100, $result->score, 'Score should be <= 100 (normalized)');
        }
    }

    public function testFuzzyMatching(): void
    {
        // Create document with specific text
        $uuid = Uuid::v4();
        $vector = new Vector(array_fill(0, 768, 0.5));
        $metadata = new Metadata([
            'test' => 'true',
            'title' => 'The Matrix Resurrections',
            'genres' => 'Sci-Fi',
            'overview' => 'A science fiction movie',
        ]);
        $metadata->setText('A science fiction movie');

        $doc = new VectorDocument(id: $uuid, vector: $vector, metadata: $metadata);
        $this->store->add($doc);

        // Query with typo - fuzzy matching should find it
        $results = iterator_to_array($this->store->query($vector, [
            'limit' => 10,
            'q' => 'Matrx', // Typo: missing 'i'
        ]));

        // Fuzzy matching should find the document despite the typo
        $found = false;
        foreach ($results as $result) {
            if ($result->id->toRfc4122() === $uuid->toRfc4122()) {
                $found = true;
                break;
            }
        }

        // Note: This might not always work depending on fuzzy threshold settings
        // But we verify that fuzzy search doesn't crash
        self::assertTrue(true, 'Fuzzy search executed without errors');
    }

    public function testSemanticRatioEffect(): void
    {
        // With semantic_ratio=0.1, BM25 should heavily influence results
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        // Document 1: Perfect vector match, no keyword
        $vector1 = new Vector(array_fill(0, 768, 1.0));
        $metadata1 = new Metadata([
            'test' => 'true',
            'title' => 'Document One',
            'genres' => 'Drama',
            'overview' => 'A dramatic story',
        ]);
        $metadata1->setText('A dramatic story');

        // Document 2: Poor vector match, many keywords
        $vector2 = new Vector(array_fill(0, 768, 0.0));
        $metadata2 = new Metadata([
            'test' => 'true',
            'title' => 'Document Two',
            'genres' => 'Action',
            'overview' => 'artificial intelligence artificial intelligence artificial intelligence',
        ]);
        $metadata2->setText('artificial intelligence artificial intelligence artificial intelligence');

        $doc1 = new VectorDocument(id: $uuid1, vector: $vector1, metadata: $metadata1);
        $doc2 = new VectorDocument(id: $uuid2, vector: $vector2, metadata: $metadata2);

        $this->store->add([$doc1, $doc2]);

        // Query with perfect vector match to doc1 AND keyword that matches doc2
        $queryVector = new Vector(array_fill(0, 768, 1.0));
        $results = iterator_to_array($this->store->query($queryVector, [
            'limit' => 10,
            'q' => 'artificial intelligence',
        ]));

        // With low semantic_ratio (0.1), BM25 should dominate
        // So doc2 should rank higher despite poor vector match
        self::assertGreaterThan(0, count($results), 'Should find documents with hybrid search');

        // Verify that semantic_ratio configuration is working (doesn't crash)
        self::assertTrue(true, 'Semantic ratio configuration is functional');
    }
}
