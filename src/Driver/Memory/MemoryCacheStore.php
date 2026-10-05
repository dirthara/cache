<?php

declare(strict_types=1);

namespace Dirthara\Cache\Driver\Memory;

use Dirthara\Cache\Contract\CacheStore;
use Dirthara\Cache\ValueObject\StoredValue;

use function array_key_exists;

final class MemoryCacheStore implements CacheStore
{
    /**
     * @var array<string, StoredValue>
     */
    private array $values = [];

    public function get(string $key): ?StoredValue
    {
        return $this->values[$key] ?? null;
    }

    public function getMultiple(array $keys): array
    {
        $found = [];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->values)) {
                continue;
            }

            $found[$key] = $this->values[$key];
        }

        return $found;
    }

    public function put(string $key, StoredValue $value): bool
    {
        $this->values[$key] = $value;

        return true;
    }

    public function putMultiple(array $values): bool
    {
        foreach ($values as $key => $value) {
            $this->values[$key] = $value;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->values[$key]);
        }

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }
}
