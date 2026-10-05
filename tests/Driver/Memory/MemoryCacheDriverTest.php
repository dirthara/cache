<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Driver\Memory;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Cache\ValueObject\StoredValue;
use PHPUnit\Framework\Attributes\UsesClass;
use Dirthara\Cache\Config\CacheConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;
use Dirthara\Cache\Driver\Memory\MemoryCacheDriver;

#[CoversClass(MemoryCacheDriver::class)]
#[UsesClass(MemoryCacheStore::class)]
#[UsesClass(StoredValue::class)]
#[UsesClass(CacheConfiguration::class)]
final class MemoryCacheDriverTest extends TestCase
{
    #[Test]
    public function it_creates_a_memory_store(): void
    {
        $store = new MemoryCacheDriver()->create(new CacheConfiguration('memory'));

        self::assertInstanceOf(MemoryCacheStore::class, $store);
    }

    #[Test]
    public function it_creates_a_separate_store_each_time(): void
    {
        $driver = new MemoryCacheDriver();
        $configuration = new CacheConfiguration('memory');
        $first = $driver->create($configuration);
        $second = $driver->create($configuration);

        $first->put('user.42', new StoredValue('payload', null));

        self::assertNotSame($first, $second);
        self::assertNull($second->get('user.42'));
    }

    #[Test]
    public function it_ignores_the_options_of_the_configuration(): void
    {
        $store = new MemoryCacheDriver()->create(new CacheConfiguration('memory', ['host' => ['not', 'a', 'string']]));

        self::assertInstanceOf(MemoryCacheStore::class, $store);
    }
}
