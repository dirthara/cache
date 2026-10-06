<?php

declare(strict_types=1);

namespace Dirthara\Cache;

use Generator;
use DateInterval;
use Psr\Cache\CacheItemInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\Cache\CacheItemPoolInterface;
use Dirthara\Cache\Exception\CachePoolException;
use Psr\Cache\CacheException as PsrCacheException;
use Dirthara\Cache\Exception\InvalidCacheKeyException;
use Psr\SimpleCache\CacheException as SimpleCacheException;
use Psr\Cache\InvalidArgumentException as PsrInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

use function is_int;
use function array_map;
use function is_string;
use function array_keys;

final readonly class SimpleCache implements CacheInterface
{
    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {}

    /**
     * @throws SimpleCacheException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $item = $this->pool->getItem($key);

            return $item->isHit() ? $item->get() : $default;
        } catch (PsrCacheException $exception) {
            throw $this->translated($exception);
        }
    }

    /**
     * @throws SimpleCacheException
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        try {
            return $this->pool->save($this->pool->getItem($key)->set($value)->expiresAfter($ttl));
        } catch (PsrCacheException $exception) {
            throw $this->translated($exception);
        }
    }

    /**
     * @throws SimpleCacheException
     */
    public function delete(string $key): bool
    {
        try {
            return $this->pool->deleteItem($key);
        } catch (PsrCacheException $exception) {
            throw $this->translated($exception);
        }
    }

    /**
     * @throws SimpleCacheException
     */
    public function clear(): bool
    {
        try {
            return $this->pool->clear();
        } catch (PsrCacheException $exception) {
            throw $this->translated($exception);
        }
    }

    /**
     * @param iterable<mixed> $keys
     *
     * @throws SimpleCacheException
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        try {
            $values = [];
            $strings = [];

            foreach ($this->items($this->keys($keys)) as $item) {
                $strings[] = $item->getKey();
                $values[] = $item->isHit() ? $item->get() : $default;
            }

            return $this->keyedValues($strings, $values);
        } catch (PsrCacheException $exception) {
            throw $this->translated($exception);
        }
    }

    /**
     * @param iterable<mixed, mixed> $values
     *
     * @throws SimpleCacheException
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        try {
            $pairs = [];

            // @mago-expect analysis:mixed-assignment Each key is checked before it is used
            // @mago-expect analysis:mixed-assignment A value can be anything the application caches
            foreach ($values as $key => $value) {
                $pairs[$this->key($key)] = $value;
            }

            $deferred = true;

            foreach ($this->items(array_map(
                static fn(string|int $key): string => (string) $key,
                array_keys($pairs),
            )) as $key => $item) {
                $deferred = $this->pool->saveDeferred($item->set($pairs[$key])->expiresAfter($ttl)) && $deferred;
            }

            return $this->pool->commit() && $deferred;
        } catch (PsrCacheException $exception) {
            throw $this->translated($exception);
        }
    }

    /**
     * @param iterable<mixed> $keys
     *
     * @throws SimpleCacheException
     */
    public function deleteMultiple(iterable $keys): bool
    {
        try {
            return $this->pool->deleteItems($this->keys($keys));
        } catch (PsrCacheException $exception) {
            throw $this->translated($exception);
        }
    }

    /**
     * @throws SimpleCacheException
     */
    public function has(string $key): bool
    {
        try {
            return $this->pool->hasItem($key);
        } catch (PsrCacheException $exception) {
            throw $this->translated($exception);
        }
    }

    private function translated(PsrCacheException $exception): SimpleCacheException
    {
        if ($exception instanceof PsrInvalidArgumentException) {
            return $exception instanceof SimpleCacheInvalidArgumentException
                ? $exception
                : InvalidCacheKeyException::rejectedByPool($exception);
        }

        return $exception instanceof SimpleCacheException
            ? $exception
            : CachePoolException::operationFailed($exception);
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
     * @throws SimpleCacheException
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
     * @throws SimpleCacheException
     *
     * @return list<string>
     */
    private function keys(iterable $keys): array
    {
        $strings = [];

        // @mago-expect analysis:mixed-assignment Each key is checked before it is used
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw InvalidCacheKeyException::notAString($key);
            }

            $strings[] = $key;
        }

        return $strings;
    }

    /**
     * @throws SimpleCacheException
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
