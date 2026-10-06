<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures\Integration;

use Dirthara\Cache\CachePool;
use Psr\Cache\CacheItemPoolInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;
use Dirthara\Cache\Tests\Fixtures\Integration\Fixtures\SystemClock;
use Cache\IntegrationTests\CachePoolTest as IntegrationCachePoolTest;

#[CoversNothing]
final class CachePoolTest extends IntegrationCachePoolTest
{
    private ?MemoryCacheStore $store = null;

    public function createCachePool(): CacheItemPoolInterface
    {
        $this->store ??= new MemoryCacheStore();

        return new CachePool($this->store, new NativeCacheSerialiser(), new SystemClock());
    }
}
