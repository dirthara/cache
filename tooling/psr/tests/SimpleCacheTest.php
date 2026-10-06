<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures\Integration;

use Dirthara\Cache\CachePool;
use Dirthara\Cache\SimpleCache;
use Psr\SimpleCache\CacheInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;
use Dirthara\Cache\Tests\Fixtures\Integration\Fixtures\SystemClock;
use Cache\IntegrationTests\SimpleCacheTest as IntegrationSimpleCacheTest;

#[CoversNothing]
final class SimpleCacheTest extends IntegrationSimpleCacheTest
{
    public function createSimpleCache(): CacheInterface
    {
        $pool = new CachePool(new MemoryCacheStore(), new NativeCacheSerialiser(), new SystemClock());

        return new SimpleCache($pool);
    }
}
