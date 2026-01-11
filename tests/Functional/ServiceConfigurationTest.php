<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests to verify services are correctly configured and injectable.
 *
 * @author Ahmed EBEN HASSINE
 */
class ServiceConfigurationTest extends KernelTestCase
{
    public function testStoreInterfaceIsInjectable(): void
    {
        self::bootKernel();

        $store = self::getContainer()->get(StoreInterface::class);

        self::assertInstanceOf(StoreInterface::class, $store);
    }

    public function testManagedStoreInterfaceIsImplemented(): void
    {
        self::bootKernel();

        $store = self::getContainer()->get(StoreInterface::class);

        self::assertInstanceOf(ManagedStoreInterface::class, $store);
    }

    public function testStoreServiceIsAccessibleById(): void
    {
        self::bootKernel();

        $store = self::getContainer()->get('ai.store.postgres.movies');

        self::assertInstanceOf(StoreInterface::class, $store);
    }

    public function testVectorizerIsInjectable(): void
    {
        self::bootKernel();

        $vectorizer = self::getContainer()->get(Vectorizer::class);

        self::assertInstanceOf(Vectorizer::class, $vectorizer);
    }

    public function testVectorizerServiceIsAccessibleById(): void
    {
        self::bootKernel();

        $vectorizer = self::getContainer()->get('ai.vectorizer.ollama');

        self::assertInstanceOf(Vectorizer::class, $vectorizer);
    }

    public function testPdoConnectionIsAvailable(): void
    {
        self::bootKernel();

        $dbalConnection = self::getContainer()->get('doctrine.dbal.default_connection');
        $pdo = $dbalConnection->getNativeConnection();

        self::assertInstanceOf(\PDO::class, $pdo);
    }

    public function testStoreIsLazy(): void
    {
        self::bootKernel();

        $definition = self::getContainer()->get('ai.store.postgres.hybrid');

        // Lazy services are wrapped in proxy objects
        $reflection = new \ReflectionClass($definition);
        $className = $reflection->getName();

        // Symfony lazy services typically have proxy-related names
        self::assertTrue(
            str_contains($className, 'Proxy') || str_contains($className, 'Ghost'),
            sprintf('Expected lazy service proxy but got: %s', $className)
        );
    }

    public function testStoreImplementsRequiredInterfaces(): void
    {
        self::bootKernel();

        $store = self::getContainer()->get('ai.store.postgres.movies');

        self::assertInstanceOf(StoreInterface::class, $store);
        self::assertInstanceOf(ManagedStoreInterface::class, $store);
    }

    public function testMultipleStoreReferencesReturnSameInstance(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $store1 = $container->get('ai.store.postgres.hybrid');
        $store2 = $container->get(StoreInterface::class);

        // Both should reference the same instance (shared service)
        self::assertSame($store1, $store2);
    }
}
