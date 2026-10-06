<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests;

use function iterator_to_array;

use TypeError;
use Dirthara\Cache\CacheItem;
use Dirthara\Cache\CachePool;
use Dirthara\Cache\SimpleCache;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Cache\ValueObject\StoredValue;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Cache\Exception\CachePoolException;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;
use Dirthara\Cache\Tests\Fixtures\ForeignCachePool;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;
use Dirthara\Cache\Exception\InvalidCacheKeyException;
use Dirthara\Cache\Tests\Fixtures\ContextualException;
use Dirthara\Cache\Tests\Fixtures\ForeignCacheException;
use Dirthara\Cache\Exception\CacheSerialisationException;
use Psr\SimpleCache\CacheException as SimpleCacheException;
use Dirthara\Cache\Tests\Fixtures\ForeignInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

#[CoversClass(SimpleCache::class)]
#[UsesClass(CacheItem::class)]
#[UsesClass(CachePool::class)]
#[UsesClass(StoredValue::class)]
#[UsesClass(MemoryCacheStore::class)]
#[UsesClass(NativeCacheSerialiser::class)]
#[CoversClass(InvalidCacheKeyException::class)]
#[CoversClass(CachePoolException::class)]
#[UsesClass(CacheSerialisationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SimpleCacheExceptionTest extends TestCase
{
    /** @return iterable<string, array{callable(SimpleCache): mixed}> */
    public static function operations(): iterable
    {
        yield 'get' => [static fn(SimpleCache $cache): mixed => $cache->get('key')];
        yield 'set' => [static fn(SimpleCache $cache): mixed => $cache->set('key', 'value')];
        yield 'delete' => [static fn(SimpleCache $cache): mixed => $cache->delete('key')];
        yield 'clear' => [static fn(SimpleCache $cache): mixed => $cache->clear()];
        yield 'has' => [static fn(SimpleCache $cache): mixed => $cache->has('key')];
        yield 'getMultiple' => [
            static fn(SimpleCache $cache): mixed => iterator_to_array($cache->getMultiple(['key'])),
        ];
        yield 'setMultiple' => [static fn(SimpleCache $cache): mixed => $cache->setMultiple(['key' => 'value'])];
        yield 'deleteMultiple' => [static fn(SimpleCache $cache): mixed => $cache->deleteMultiple(['key'])];
    }

    /** @param callable(SimpleCache): mixed $operation */
    #[Test]
    #[DataProvider('operations')]
    public function it_translates_foreign_cache_failures(callable $operation): void
    {
        foreach ([
            new ForeignCacheException('secret backend'),
            new ForeignInvalidArgumentException('secret key'),
        ] as $failure) {
            try {
                $operation(new SimpleCache(new ForeignCachePool($failure)));
                self::fail('A cache failure was swallowed.');
            } catch (SimpleCacheException $exception) {
                self::assertSame($failure, $exception->getPrevious());
                self::assertInstanceOf(
                    $failure instanceof ForeignInvalidArgumentException
                        ? SimpleCacheInvalidArgumentException::class
                        : CachePoolException::class,
                    $exception,
                );
                self::assertStringNotContainsString('secret', $exception->getMessage());
            }
        }
    }

    /** @param callable(SimpleCache): mixed $operation */
    #[Test]
    #[DataProvider('operations')]
    public function it_preserves_exceptions_that_already_satisfy_psr_16(callable $operation): void
    {
        foreach ([new ContextualException('failure'), InvalidCacheKeyException::empty()] as $failure) {
            try {
                $operation(new SimpleCache(new ForeignCachePool($failure)));
                self::fail('A cache failure was swallowed.');
            } catch (SimpleCacheException $exception) {
                self::assertSame($failure, $exception);
            }
        }
    }

    /** @param callable(SimpleCache): mixed $operation */
    #[Test]
    #[DataProvider('operations')]
    public function it_does_not_translate_programmer_errors(callable $operation): void
    {
        $pool = self::createStub(CacheItemPoolInterface::class);
        foreach (['getItem', 'getItems', 'hasItem', 'clear', 'deleteItem', 'deleteItems'] as $method) {
            $pool->method($method)->willThrowException(new TypeError('programmer error'));
        }
        $this->expectException(TypeError::class);

        $operation(new SimpleCache($pool));
    }

    /** @return iterable<string, array{string}> */
    public static function writingFailures(): iterable
    {
        yield 'save' => ['save'];
        yield 'saveDeferred' => ['saveDeferred'];
        yield 'commit' => ['commit'];
    }

    #[Test]
    #[DataProvider('writingFailures')]
    public function it_translates_failures_after_an_item_has_been_fetched(string $method): void
    {
        $pool = self::createStub(CacheItemPoolInterface::class);
        $item = self::createStub(CacheItemInterface::class);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();
        $pool->method('getItem')->willReturn($item);
        $pool->method('getItems')->willReturn(['key' => $item]);
        if ($method !== 'saveDeferred') {
            $pool->method('saveDeferred')->willReturn(true);
        }
        $failure = new ForeignCacheException('backend details');
        $pool->method($method)->willThrowException($failure);
        $cache = new SimpleCache($pool);
        try {
            if ($method === 'save') {
                $cache->set('key', 'value');
            }
            if ($method !== 'save') {
                $cache->setMultiple(['key' => 'value']);
            }
            self::fail('A cache failure was swallowed.');
        } catch (CachePoolException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }
    }
}
