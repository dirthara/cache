<?php

declare(strict_types=1);

namespace Dirthara\Cache\Contract;

use DateTimeImmutable;
use Dirthara\Cache\ValueObject\StoredValue;

interface CacheStore
{
    public function get(string $key): StoredValue;

    /**
     * @param list<string> $keys
     *
     * @return array<string, StoredValue>
     */
    public function getMultiple(array $keys): array;

    public function put(string $key, mixed $value, ?DateTimeImmutable $expiresAt): void;

    /**
     * @param array<string, StoredValue> $values
     */
    public function putMultiple(array $values): void;

    public function delete(string $key): void;

    /**
     * @param list<string> $keys
     */
    public function deleteMultiple(array $keys): void;

    public function clear(): void;
}
