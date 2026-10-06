<?php

declare(strict_types=1);

namespace Dirthara\Cache;

use Closure;
use Generator;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Dirthara\Cache\Contract\CacheStore;
use Dirthara\Cache\ValueObject\StoredValue;
use Dirthara\Cache\Contract\CacheSerialiser;
use Dirthara\Cache\Exception\CacheException;
use Dirthara\Cache\Exception\InvalidCacheKeyException;

use function strpbrk;
use function array_map;
use function is_string;
use function array_diff;
use function array_keys;
use function array_unique;
use function array_values;
use function array_key_exists;

final class CachePool implements CacheItemPoolInterface
{
    private const string RESERVED_CHARACTERS = '{}()/\\@:';

    /**
     * @var array<array-key, StoredValue|null>
     */
    private array $deferred = [];

    public function __construct(
        private CacheStore $store,
        private CacheSerialiser $serialiser,
        private ClockInterface $clock,
    ) {}

    public function __destruct()
    {
        $this->commit();
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function getItem(string $key): CacheItemInterface
    {
        $this->validate($key);

        return $this->item($key, array_key_exists($key, $this->deferred) ? $this->deferred[$key] : $this->fetch($key));
    }

    /**
     * @param array<mixed> $keys
     *
     * @throws InvalidCacheKeyException
     *
     * @return iterable<string, CacheItem>
     */
    public function getItems(array $keys = []): iterable
    {
        $keys = $this->validateAll($keys);
        /** @var list<string> $unfetched */
        $unfetched = array_values(array_diff($keys, array_keys($this->deferred)));
        $fetched = $this->fetchMultiple($unfetched);
        $items = [];

        foreach ($keys as $key) {
            $items[] = $this->item(
                $key,
                array_key_exists($key, $this->deferred) ? $this->deferred[$key] : $fetched[$key] ?? null,
            );
        }

        return $this->keyedItems($keys, $items);
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        $this->deferred = [];

        return $this->attempt(fn(): bool => $this->store->clear());
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function deleteItem(string $key): bool
    {
        $this->validate($key);

        unset($this->deferred[$key]);

        return $this->attempt(fn(): bool => $this->store->delete($key));
    }

    /**
     * @param array<mixed> $keys
     *
     * @throws InvalidCacheKeyException
     */
    public function deleteItems(array $keys): bool
    {
        $keys = $this->validateAll($keys);

        foreach ($keys as $key) {
            unset($this->deferred[$key]);
        }

        return $this->attempt(fn(): bool => $this->store->deleteMultiple($keys));
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }

        $key = $item->getKey();
        $this->validate($key);

        unset($this->deferred[$key]);

        if ($this->expired($item->expiry)) {
            return $this->attempt(fn(): bool => $this->store->delete($key));
        }

        $stored = $this->stored($item);

        return $stored !== null && $this->persist($key, $stored);
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }

        $key = $item->getKey();
        $this->validate($key);

        if ($this->expired($item->expiry)) {
            $this->deferred[$key] = null;

            return true;
        }

        $stored = $this->stored($item);

        if ($stored === null) {
            return false;
        }

        $this->deferred[$key] = $stored;

        return true;
    }

    public function commit(): bool
    {
        $values = [];
        $expired = [];

        foreach ($this->deferred as $key => $stored) {
            if ($stored !== null && !$this->expired($stored->expiresAt)) {
                $values[$key] = $stored;

                continue;
            }

            $expired[] = (string) $key;
        }

        $saved = $values === [] || $this->attempt(fn(): bool => $this->store->putMultiple($values));
        $deleted = $expired === [] || $this->attempt(fn(): bool => $this->store->deleteMultiple($expired));

        if ($saved && $deleted) {
            $this->deferred = [];
        }

        return $saved && $deleted;
    }

    /**
     * @param list<string> $keys
     * @param list<CacheItem> $items
     *
     * @return Generator<string, CacheItem>
     */
    private function keyedItems(array $keys, array $items): Generator
    {
        foreach ($keys as $index => $key) {
            yield $key => $items[$index];
        }
    }

    private function item(string $key, ?StoredValue $stored): CacheItem
    {
        if ($stored === null || $this->expired($stored->expiresAt)) {
            return CacheItem::miss($key, $this->clock);
        }

        try {
            // @mago-expect analysis:mixed-assignment A cached value can be anything the application stored
            $value = $this->serialiser->deserialise($stored->payload);
        } catch (CacheException) {
            return CacheItem::miss($key, $this->clock);
        }

        return CacheItem::hit($key, $value, $this->clock);
    }

    private function fetch(string $key): ?StoredValue
    {
        try {
            return $this->store->get($key);
        } catch (CacheException) {
            return null;
        }
    }

    /**
     * @param list<string> $keys
     *
     * @return array<array-key, StoredValue>
     */
    private function fetchMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        try {
            return $this->store->getMultiple($keys);
        } catch (CacheException) {
            return [];
        }
    }

    private function persist(string $key, StoredValue $stored): bool
    {
        return $this->attempt(fn(): bool => $this->expired($stored->expiresAt)
            ? $this->store->delete($key)
            : $this->store->put($key, $stored));
    }

    private function stored(CacheItem $item): ?StoredValue
    {
        try {
            return new StoredValue($this->serialiser->serialise($item->value), $item->expiry);
        } catch (CacheException) {
            return null;
        }
    }

    private function expired(?DateTimeImmutable $expiry): bool
    {
        return $expiry !== null && $expiry <= $this->clock->now();
    }

    /**
     * @param Closure(): bool $operation
     */
    private function attempt(Closure $operation): bool
    {
        try {
            return $operation();
        } catch (CacheException) {
            return false;
        }
    }

    /**
     * @throws InvalidCacheKeyException
     */
    private function validate(mixed $key): string
    {
        if (!is_string($key)) {
            throw InvalidCacheKeyException::notAString($key);
        }

        if ($key === '') {
            throw InvalidCacheKeyException::empty();
        }

        if (strpbrk($key, self::RESERVED_CHARACTERS) !== false) {
            throw InvalidCacheKeyException::reservedCharacters($key, self::RESERVED_CHARACTERS);
        }

        return $key;
    }

    /**
     * @param array<mixed> $keys
     *
     * @throws InvalidCacheKeyException
     *
     * @return list<string>
     */
    private function validateAll(array $keys): array
    {
        return array_values(array_unique(array_map($this->validate(...), $keys)));
    }
}
