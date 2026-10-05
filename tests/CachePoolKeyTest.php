<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests;

use Dirthara\Cache\CacheItem;
use Dirthara\Cache\CachePool;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Cache\ValueObject\StoredValue;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use Dirthara\Cache\Tests\Fixtures\TestClock;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;
use Dirthara\Cache\Exception\InvalidCacheKeyException;
use Dirthara\Cache\Tests\Fixtures\RecordingCacheStore;
use Dirthara\Cache\Exception\CacheSerialisationException;

use function str_repeat;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

#[CoversClass(CachePool::class)]
#[UsesClass(CacheItem::class)]
#[UsesClass(StoredValue::class)]
#[UsesClass(MemoryCacheStore::class)]
#[UsesClass(NativeCacheSerialiser::class)]
#[UsesClass(InvalidCacheKeyException::class)]
#[UsesClass(CacheSerialisationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class CachePoolKeyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validKeys(): iterable
    {
        yield 'letters and digits' => ['User42'];
        yield 'dots and underscores' => ['user.profile_42'];
        yield '64 characters' => [str_repeat('k', times: 64)];
        yield 'longer than 64 characters' => [str_repeat('k', times: 300)];
        yield 'spaces, dashes, and other characters' => ['user 42-profile#ä'];
        yield 'a numeric key' => ['42'];
    }

    #[Test]
    #[DataProvider('validKeys')]
    public function it_accepts_a_valid_key(string $key): void
    {
        $pool = $this->pool(new RecordingCacheStore());

        self::assertTrue($pool->save($pool->getItem($key)->set('value')));
        self::assertSame('value', $pool->getItem($key)->get());
        self::assertSame('value', $pool->getItems([$key])[$key]->get());
        self::assertTrue($pool->deleteItem($key));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedCharacters(): iterable
    {
        foreach (['{', '}', '(', ')', '/', '\\', '@', ':'] as $character) {
            yield $character => [$character];
        }
    }

    #[Test]
    #[DataProvider('reservedCharacters')]
    public function it_refuses_a_key_with_a_reserved_character(string $character): void
    {
        try {
            $this->pool(new RecordingCacheStore())->getItem('user' . $character . '42');
            self::fail('A key with a reserved character was accepted.');
        } catch (InvalidCacheKeyException $exception) {
            self::assertInstanceOf(CacheInvalidArgumentException::class, $exception);
            self::assertInstanceOf(SimpleCacheInvalidArgumentException::class, $exception);
            self::assertSame(['key' => 'user' . $character . '42'], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_an_empty_key(): void
    {
        $this->expectExceptionObject(InvalidCacheKeyException::empty());

        $this->pool(new RecordingCacheStore())->getItem('');
    }

    /**
     * @return iterable<string, array{callable(CachePool): mixed}>
     */
    public static function operations(): iterable
    {
        yield 'getItem' => [static fn(CachePool $pool): mixed => $pool->getItem('user:42')];
        yield 'getItems' => [static fn(CachePool $pool): mixed => $pool->getItems(['user.1', 'user:42'])];
        yield 'hasItem' => [static fn(CachePool $pool): mixed => $pool->hasItem('user:42')];
        yield 'deleteItem' => [static fn(CachePool $pool): mixed => $pool->deleteItem('user:42')];
        yield 'deleteItems' => [static fn(CachePool $pool): mixed => $pool->deleteItems(['user.1', 'user:42'])];
        yield 'save' => [static fn(CachePool $pool): mixed => $pool->save(CacheItem::miss('user:42', new TestClock()))];
        yield 'saveDeferred' => [
            static fn(CachePool $pool): mixed => $pool->saveDeferred(CacheItem::miss('user:42', new TestClock())),
        ];
    }

    /**
     * @param callable(CachePool): mixed $operation
     */
    #[Test]
    #[DataProvider('operations')]
    public function it_refuses_an_invalid_key_in_every_operation_without_touching_the_store(callable $operation): void
    {
        $store = new RecordingCacheStore();

        try {
            $operation($this->pool($store));
            self::fail('An invalid key was accepted.');
        } catch (InvalidCacheKeyException) {
            self::assertSame([], $store->calls);
        }
    }

    #[Test]
    public function it_refuses_a_key_that_is_not_a_string(): void
    {
        try {
            $this->pool(new RecordingCacheStore())->getItems(['user.1', 42]);
            self::fail('A key that is not a string was accepted.');
        } catch (InvalidCacheKeyException $exception) {
            self::assertSame(['type' => 'int'], $exception->context);
        }
    }

    private function pool(RecordingCacheStore $store, ?TestClock $clock = null): CachePool
    {
        return new CachePool($store, new NativeCacheSerialiser(), $clock ?? new TestClock());
    }
}
