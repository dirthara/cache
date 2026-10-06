<?php

declare(strict_types=1);

namespace Dirthara\Cache\Contract;

use Dirthara\Cache\ValueObject\StoredValue;

interface CacheStore
{
    public function get(string $key): ?StoredValue;

    /**
     * @param list<string> $keys
     *
     * @return array<array-key, StoredValue>
     */
    public function getMultiple(array $keys): array;

    public function put(string $key, StoredValue $value): bool;

    /**
     * @param array<array-key, StoredValue> $values
     */
    public function putMultiple(array $values): bool;

    public function delete(string $key): bool;

    /**
     * @param list<string> $keys
     */
    public function deleteMultiple(array $keys): bool;

    public function clear(): bool;
}
