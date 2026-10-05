<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests;

use Dirthara\Cache\CacheItem;
use Dirthara\Cache\CachePool;
use PHPUnit\Framework\TestCase;
use Dirthara\Cache\CacheFactory;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Cache\ValueObject\StoredValue;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use Dirthara\Cache\Tests\Fixtures\TestClock;
use Dirthara\Cache\Config\CacheConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Cache\Driver\CacheDriverRegistry;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;
use Dirthara\Cache\Driver\Memory\MemoryCacheDriver;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;
use Dirthara\Cache\Tests\Fixtures\RecordingCacheDriver;
use Dirthara\Cache\Exception\CacheDriverNotFoundException;

#[CoversClass(CacheFactory::class)]
#[UsesClass(CacheItem::class)]
#[UsesClass(CachePool::class)]
#[UsesClass(StoredValue::class)]
#[UsesClass(MemoryCacheStore::class)]
#[UsesClass(MemoryCacheDriver::class)]
#[UsesClass(CacheConfiguration::class)]
#[UsesClass(CacheDriverRegistry::class)]
#[UsesClass(NativeCacheSerialiser::class)]
#[UsesClass(CacheDriverNotFoundException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class CacheFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_pool_on_a_store_from_the_configured_driver(): void
    {
        $drivers = new CacheDriverRegistry();
        $drivers->register('memory', new MemoryCacheDriver());

        $pool = $this->factory($drivers)->create(new CacheConfiguration('memory'));
        $pool->save($pool->getItem('user.42')->set('Ada'));

        self::assertInstanceOf(CachePool::class, $pool);
        self::assertSame('Ada', $pool->getItem('user.42')->get());
    }

    #[Test]
    public function it_passes_the_configuration_to_the_driver_it_names(): void
    {
        $drivers = new CacheDriverRegistry();
        $memory = new RecordingCacheDriver();
        $redis = new RecordingCacheDriver();
        $drivers->register('memory', $memory);
        $drivers->register('redis', $redis);
        $configuration = new CacheConfiguration('redis', ['host' => 'localhost']);

        $this->factory($drivers)->create($configuration);

        self::assertSame([$configuration], $redis->configurations);
        self::assertSame([], $memory->configurations);
    }

    #[Test]
    public function it_creates_a_separate_pool_each_time(): void
    {
        $drivers = new CacheDriverRegistry();
        $drivers->register('memory', new MemoryCacheDriver());
        $factory = $this->factory($drivers);

        $first = $factory->create(new CacheConfiguration('memory'));
        $second = $factory->create(new CacheConfiguration('memory'));
        $first->save($first->getItem('user.42')->set('Ada'));

        self::assertNotSame($first, $second);
        self::assertFalse($second->hasItem('user.42'));
    }

    #[Test]
    public function it_refuses_a_configuration_naming_a_driver_that_is_not_registered(): void
    {
        $this->expectExceptionObject(CacheDriverNotFoundException::for('redis'));

        $this->factory(new CacheDriverRegistry())->create(new CacheConfiguration('redis'));
    }

    private function factory(CacheDriverRegistry $drivers): CacheFactory
    {
        return new CacheFactory($drivers, new NativeCacheSerialiser(), new TestClock());
    }
}
