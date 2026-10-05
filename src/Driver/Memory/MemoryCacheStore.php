<?php

declare(strict_types=1);

namespace Dirthara\Cache\Driver\Memory;

use DateTimeImmutable;
use Dirthara\Cache\Contract\CacheStore;
use Dirthara\Cache\ValueObject\StoredValue;

class MemoryCacheStore implements CacheStore
{
    public function get(string $key): StoredValue
    {
        // TODO: Implement get() method.
    }

    public function getMultiple(array $keys): array
    {
        // TODO: Implement getMultiple() method.
    }

    public function put(string $key, mixed $value, ?DateTimeImmutable $expiresAt): void
    {
        // TODO: Implement put() method.
    }

    public function putMultiple(array $values): void
    {
        // TODO: Implement putMultiple() method.
    }

    public function delete(string $key): void
    {
        // TODO: Implement delete() method.
    }

    public function deleteMultiple(array $keys): void
    {
        // TODO: Implement deleteMultiple() method.
    }

    public function clear(): void
    {
        // TODO: Implement clear() method.
    }
}
