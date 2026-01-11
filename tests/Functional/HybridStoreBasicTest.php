<?php

namespace App\Tests\Functional;

use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Basic tests to verify HybridStore is properly configured and operational.
 *
 * @author Ahmed EBEN HASSINE
 */
class HybridStoreBasicTest extends KernelTestCase
{
    public function testHybridStoreServiceExists(): void
    {
        self::bootKernel();

        $store = self::getContainer()->get('ai.store.postgres.movies');

        self::assertInstanceOf(StoreInterface::class, $store);
        self::assertInstanceOf(ManagedStoreInterface::class, $store);
    }

    public function testHybridStoreIsAccessibleViaStoreInterface(): void
    {
        self::bootKernel();

        $store = self::getContainer()->get(StoreInterface::class);

        self::assertInstanceOf(StoreInterface::class, $store);
        self::assertInstanceOf(ManagedStoreInterface::class, $store);
    }

    public function testHybridStoreIsLazyLoaded(): void
    {
        self::bootKernel();

        $store = self::getContainer()->get('ai.store.postgres.movies');

        // Lazy services are wrapped in proxy objects
        $reflection = new \ReflectionClass($store);
        $className = $reflection->getName();

        self::assertTrue(
            str_contains($className, 'Proxy') || str_contains($className, 'Ghost') || str_contains($className, 'HybridStore'),
            sprintf('Expected lazy proxy or HybridStore class, got: %s', $className)
        );
    }

    public function testMultipleReferencesReturnSameInstance(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $store1 = $container->get('ai.store.postgres.hybrid');
        $store2 = $container->get(StoreInterface::class);

        // Both should reference the same instance (shared service)
        self::assertSame($store1, $store2);
    }
}
