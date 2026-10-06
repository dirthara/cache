<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures;

use Generator;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class ForeignCachePool implements CacheItemPoolInterface
{
    public function __construct(
        private CacheException $failure,
    ) {}

    public function getItem(string $key): CacheItemInterface
    {
        throw $this->failure;
    }

    /** @param array<mixed> $keys */
    public function getItems(array $keys = []): iterable
    {
        return $this->failedItems();
    }

    public function hasItem(string $key): bool
    {
        throw $this->failure;
    }

    public function clear(): bool
    {
        throw $this->failure;
    }

    public function deleteItem(string $key): bool
    {
        throw $this->failure;
    }

    /** @param array<mixed> $keys */
    public function deleteItems(array $keys): bool
    {
        throw $this->failure;
    }

    public function save(CacheItemInterface $item): bool
    {
        throw $this->failure;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        throw $this->failure;
    }

    public function commit(): bool
    {
        throw $this->failure;
    }

    /** @return Generator<string, CacheItemInterface> */
    private function failedItems(): Generator
    {
        yield 'key' => $this->getItem('key');
    }
}
