<?php

declare(strict_types=1);

namespace Dirthara\Cache\Driver\Memory;

use Dirthara\Cache\Contract\CacheStore;
use Dirthara\Cache\ValueObject\StoredValue;

final readonly class MemoryCacheStore implements CacheStore
{
    public function get(string $key): ?StoredValue
    {
        // TODO: Implement get() method.
    }

    public function getMultiple(array $keys): array
    {
        // TODO: Implement getMultiple() method.
    }

    public function put(string $key, mixed $value): bool
    {
        // TODO: Implement put() method.
    }

    public function putMultiple(array $values): bool
    {
        // TODO: Implement putMultiple() method.
    }

    public function delete(string $key): bool
    {
        // TODO: Implement delete() method.
    }

    public function deleteMultiple(array $keys): bool
    {
        // TODO: Implement deleteMultiple() method.
    }

    public function clear(): bool
    {
        // TODO: Implement clear() method.
    }
}
