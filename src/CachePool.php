<?php

declare(strict_types=1);

namespace Dirthara\Cache;

use Psr\Clock\ClockInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Dirthara\Cache\Contract\CacheStore;
use Dirthara\Cache\Contract\CacheSerialiser;

final class CachePool implements CacheItemPoolInterface
{
    /**
     * @var array<string, CacheItem>
     */
    private array $deferred = [];

    public function __construct(
        private CacheStore $store,
        private CacheSerialiser $serialiser,
        private ClockInterface $clock,
    ) {}

    public function getItem(string $key): CacheItemInterface
    {
        // TODO: Implement getItem() method.
    }

    public function getItems(array $keys = []): iterable
    {
        // TODO: Implement getItems() method.
    }

    public function hasItem(string $key): bool
    {
        // TODO: Implement hasItem() method.
    }

    public function clear(): bool
    {
        // TODO: Implement clear() method.
    }

    public function deleteItem(string $key): bool
    {
        // TODO: Implement deleteItem() method.
    }

    public function deleteItems(array $keys): bool
    {
        // TODO: Implement deleteItems() method.
    }

    public function save(CacheItemInterface $item): bool
    {
        // TODO: Implement save() method.
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        // TODO: Implement saveDeferred() method.
    }

    public function commit(): bool
    {
        // TODO: Implement commit() method.
    }
}
