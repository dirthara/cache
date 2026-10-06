<?php

declare(strict_types=1);

namespace Dirthara\Cache;

use Generator;
use DateInterval;
use Psr\Cache\CacheItemInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\Cache\CacheItemPoolInterface;
use Dirthara\Cache\Exception\InvalidCacheKeyException;

use function is_int;
use function is_string;
use function array_keys;

final readonly class SimpleCache implements CacheInterface
{
    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {}

    /**
     * @throws InvalidCacheKeyException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->pool->getItem($key);

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return $this->pool->save($this->pool->getItem($key)->set($value)->expiresAfter($ttl));
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function delete(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    public function clear(): bool
    {
        return $this->pool->clear();
    }

    /**
     * @param iterable<mixed> $keys
     *
     * @throws InvalidCacheKeyException
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        $strings = [];

        foreach ($this->items($this->keys($keys)) as $item) {
            $strings[] = $item->getKey();
            $values[] = $item->isHit() ? $item->get() : $default;
        }

        return $this->keyedValues($strings, $values);
    }

    /**
     * @param iterable<mixed, mixed> $values
     *
     * @throws InvalidCacheKeyException
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $pairs = [];

        // @mago-expect analysis:mixed-assignment Each key is checked before it is used
        // @mago-expect analysis:mixed-assignment A value can be anything the application caches
        foreach ($values as $key => $value) {
            $pairs[$this->key($key)] = $value;
        }

        $deferred = true;

        foreach ($this->items($this->keys(array_keys($pairs))) as $key => $item) {
            $deferred = $this->pool->saveDeferred($item->set($pairs[$key])->expiresAfter($ttl)) && $deferred;
        }

        return $this->pool->commit() && $deferred;
    }

    /**
     * @param iterable<mixed> $keys
     *
     * @throws InvalidCacheKeyException
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->pool->deleteItems($this->keys($keys));
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function has(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    /**
     * @param list<string> $keys
     * @param list<mixed> $values
     *
     * @return Generator<string, mixed>
     */
    private function keyedValues(array $keys, array $values): Generator
    {
        foreach ($keys as $index => $key) {
            yield $key => $values[$index];
        }
    }

    /**
     * @param list<string> $keys
     *
     * @throws InvalidCacheKeyException
     *
     * @return iterable<string, CacheItemInterface>
     */
    private function items(array $keys): iterable
    {
        /** @var iterable<string, CacheItemInterface> PSR-6 promises an item for each key, keyed by that key */
        return $this->pool->getItems($keys);
    }

    /**
     * @param iterable<mixed> $keys
     *
     * @throws InvalidCacheKeyException
     *
     * @return list<string>
     */
    private function keys(iterable $keys): array
    {
        $strings = [];

        // @mago-expect analysis:mixed-assignment Each key is checked before it is used
        foreach ($keys as $key) {
            $strings[] = $this->key($key);
        }

        return $strings;
    }

    /**
     * @throws InvalidCacheKeyException
     */
    private function key(mixed $key): string
    {
        return match (true) {
            is_string($key) => $key,
            is_int($key) => (string) $key,
            default => throw InvalidCacheKeyException::notAString($key),
        };
    }
}
