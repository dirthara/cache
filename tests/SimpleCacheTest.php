<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests;

use function iterator_to_array;

use stdClass;
use Generator;
use DateInterval;
use Dirthara\Cache\CacheItem;
use Dirthara\Cache\CachePool;
use Dirthara\Cache\SimpleCache;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Cache\ValueObject\StoredValue;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use Dirthara\Cache\Tests\Fixtures\TestClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;
use Dirthara\Cache\Exception\InvalidCacheKeyException;
use Dirthara\Cache\Tests\Fixtures\RecordingCacheStore;
use Dirthara\Cache\Exception\CacheSerialisationException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

#[CoversClass(SimpleCache::class)]
#[UsesClass(CacheItem::class)]
#[UsesClass(CachePool::class)]
#[UsesClass(StoredValue::class)]
#[UsesClass(MemoryCacheStore::class)]
#[UsesClass(NativeCacheSerialiser::class)]
#[UsesClass(InvalidCacheKeyException::class)]
#[UsesClass(CacheSerialisationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class SimpleCacheTest extends TestCase
{
    private RecordingCacheStore $store;

    private TestClock $clock;

    private SimpleCache $cache;

    protected function setUp(): void
    {
        $this->store = new RecordingCacheStore();
        $this->clock = new TestClock('2026-10-05 12:00:00');
        $this->cache = new SimpleCache(new CachePool($this->store, new NativeCacheSerialiser(), $this->clock));
    }

    #[Test]
    public function it_returns_the_default_for_a_key_that_was_never_set(): void
    {
        self::assertNull($this->cache->get('user.42'));
        self::assertSame('unknown', $this->cache->get('user.42', 'unknown'));
        self::assertFalse($this->cache->has('user.42'));
    }

    #[Test]
    public function it_returns_a_value_that_was_set(): void
    {
        self::assertTrue($this->cache->set('user.42', ['name' => 'Ada']));

        self::assertSame(['name' => 'Ada'], $this->cache->get('user.42', 'unknown'));
        self::assertTrue($this->cache->has('user.42'));
    }

    #[Test]
    public function it_returns_a_cached_null_rather_than_the_default(): void
    {
        $this->cache->set('user.42', null);

        self::assertNull($this->cache->get('user.42', 'unknown'));
        self::assertTrue($this->cache->has('user.42'));
    }

    #[Test]
    public function it_returns_a_copy_of_a_cached_object(): void
    {
        $user = new stdClass();
        $user->name = 'Ada';
        $this->cache->set('user.42', $user);

        $user->name = 'Grace';

        self::assertEquals((object) ['name' => 'Ada'], $this->cache->get('user.42'));
    }

    /**
     * @return iterable<string, array{DateInterval|int}>
     */
    public static function ttls(): iterable
    {
        yield 'seconds' => [60];
        yield 'an interval' => [new DateInterval('PT1M')];
    }

    #[Test]
    #[DataProvider('ttls')]
    public function it_forgets_a_value_once_its_ttl_passes(DateInterval|int $ttl): void
    {
        $this->cache->set('user.42', 'Ada', $ttl);

        $this->clock->advance('+59 seconds');
        self::assertSame('Ada', $this->cache->get('user.42'));

        $this->clock->advance('+1 second');
        self::assertSame('expired', $this->cache->get('user.42', 'expired'));
    }

    #[Test]
    public function it_keeps_a_value_without_a_ttl(): void
    {
        $this->cache->set('user.42', 'Ada');
        $this->clock->advance('+10 years');

        self::assertSame('Ada', $this->cache->get('user.42'));
    }

    /**
     * @return iterable<string, array{DateInterval|int}>
     */
    public static function ttlsThatAreNotInTheFuture(): iterable
    {
        yield 'zero' => [0];
        yield 'negative seconds' => [-1];
        yield 'a negative interval' => [DateInterval::createFromDateString('-1 minute')];
    }

    #[Test]
    #[DataProvider('ttlsThatAreNotInTheFuture')]
    public function it_deletes_a_value_set_with_a_ttl_that_is_not_in_the_future(DateInterval|int $ttl): void
    {
        $this->cache->set('user.42', 'Ada');

        self::assertTrue($this->cache->set('user.42', 'Grace', $ttl));

        self::assertFalse($this->cache->has('user.42'));
    }

    #[Test]
    #[DataProvider('ttlsThatAreNotInTheFuture')]
    public function it_deletes_an_unserialisable_replacement_before_serialising(DateInterval|int $ttl): void
    {
        $this->cache->set('key', 'old');

        self::assertTrue($this->cache->set('key', static fn(): string => 'cannot serialise', $ttl));
        self::assertFalse($this->cache->has('key'));

        $this->cache->setMultiple(['123' => 'old', 'other' => 'old']);

        self::assertTrue($this->cache->setMultiple([
            '123' => static fn(): string => 'cannot serialise',
            'other' => static fn(): string => 'cannot serialise',
        ], $ttl));
        self::assertFalse($this->cache->has('123'));
        self::assertFalse($this->cache->has('other'));
    }

    #[Test]
    public function it_fails_to_set_a_value_that_cannot_be_serialised(): void
    {
        self::assertFalse($this->cache->set('user.42', static fn(): string => 'Ada'));
        self::assertFalse($this->cache->has('user.42'));
    }

    #[Test]
    public function it_deletes_a_value(): void
    {
        $this->cache->set('user.42', 'Ada');

        self::assertTrue($this->cache->delete('user.42'));
        self::assertFalse($this->cache->has('user.42'));
        self::assertTrue($this->cache->delete('user.42'));
    }

    #[Test]
    public function it_clears_every_value(): void
    {
        $this->cache->set('first', 1);
        $this->cache->set('second', 2);

        self::assertTrue($this->cache->clear());

        self::assertFalse($this->cache->has('first'));
        self::assertFalse($this->cache->has('second'));
    }

    #[Test]
    public function it_returns_several_values_with_the_default_for_each_missing_key(): void
    {
        $this->cache->set('first', 1);
        $this->cache->set('third', null);

        self::assertSame(
            ['first' => 1, 'second' => 'missing', 'third' => null],
            iterator_to_array($this->cache->getMultiple(['first', 'second', 'third'], 'missing')),
        );
    }

    #[Test]
    public function it_reads_several_keys_from_any_iterable_at_once(): void
    {
        $this->cache->set('first', 1);
        $this->cache->set('second', 2);

        self::assertSame(
            ['first' => 1, 'second' => 2],
            iterator_to_array($this->cache->getMultiple(self::generate(['first', 'second']))),
        );
        self::assertContains(['getMultiple', ['first', 'second']], $this->store->calls);
    }

    #[Test]
    public function it_returns_no_values_for_no_keys(): void
    {
        self::assertSame([], iterator_to_array($this->cache->getMultiple([])));
    }

    #[Test]
    public function it_sets_several_values_at_once(): void
    {
        self::assertTrue($this->cache->setMultiple(['first' => 1, 'second' => 2]));

        self::assertSame(
            ['first' => 1, 'second' => 2],
            iterator_to_array($this->cache->getMultiple(['first', 'second'])),
        );
        self::assertSame(['getMultiple', 'putMultiple', 'getMultiple'], $this->store->operations());
    }

    #[Test]
    public function it_sets_several_values_from_a_generator_where_the_last_value_for_a_key_wins(): void
    {
        $values = (static function (): Generator {
            yield 'first' => 1;
            yield 'second' => 2;
            yield 'first' => 3;
        })();

        self::assertTrue($this->cache->setMultiple($values));

        self::assertSame(
            ['first' => 3, 'second' => 2],
            iterator_to_array($this->cache->getMultiple(['first', 'second'])),
        );
    }

    #[Test]
    public function it_sets_several_values_with_one_ttl(): void
    {
        $this->cache->setMultiple(['first' => 1, 'second' => 2], 60);
        $this->clock->advance('+60 seconds');

        self::assertSame(
            ['first' => null, 'second' => null],
            iterator_to_array($this->cache->getMultiple(['first', 'second'])),
        );
    }

    #[Test]
    public function it_deletes_several_values_set_with_a_ttl_that_is_not_in_the_future(): void
    {
        $this->cache->setMultiple(['first' => 1, 'second' => 2]);

        self::assertTrue($this->cache->setMultiple(['first' => 3, 'second' => 4], 0));

        self::assertSame([], $this->store->inner->getMultiple(['first', 'second']));
    }

    #[Test]
    public function it_sets_the_values_it_can_and_reports_the_one_it_cannot_serialise(): void
    {
        self::assertFalse($this->cache->setMultiple([
            'first' => 1,
            'closure' => static fn(): int => 2,
            'third' => 3,
        ]));

        self::assertSame(
            ['first' => 1, 'closure' => null, 'third' => 3],
            iterator_to_array($this->cache->getMultiple(['first', 'closure', 'third'])),
        );
    }

    #[Test]
    public function it_reports_several_values_the_store_fails_to_set(): void
    {
        $this->store->failing = true;

        self::assertFalse($this->cache->setMultiple(['first' => 1]));
    }

    #[Test]
    public function it_deletes_several_values_at_once(): void
    {
        $this->cache->setMultiple(['first' => 1, 'second' => 2, 'kept' => 3]);

        self::assertTrue($this->cache->deleteMultiple(self::generate(['first', 'second'])));

        self::assertSame(
            ['first' => null, 'second' => null, 'kept' => 3],
            iterator_to_array($this->cache->getMultiple(['first', 'second', 'kept'])),
        );
    }

    #[Test]
    public function it_accepts_numeric_keys_that_php_turned_into_integers(): void
    {
        self::assertTrue($this->cache->setMultiple(['42' => 'Ada', '7' => 'Grace']));

        self::assertSame([42 => 'Ada', 7 => 'Grace'], iterator_to_array($this->cache->getMultiple(['42', '7'])));
        self::assertSame('Ada', $this->cache->get('42'));
        self::assertTrue($this->cache->deleteMultiple(['42']));
        self::assertFalse($this->cache->has('42'));
    }

    /**
     * @return iterable<string, array{callable(SimpleCache): mixed}>
     */
    public static function operationsWithAnInvalidKey(): iterable
    {
        yield 'get' => [static fn(SimpleCache $cache): mixed => $cache->get('user:42')];
        yield 'set' => [static fn(SimpleCache $cache): mixed => $cache->set('user:42', 'Ada')];
        yield 'delete' => [static fn(SimpleCache $cache): mixed => $cache->delete('user:42')];
        yield 'has' => [static fn(SimpleCache $cache): mixed => $cache->has('')];
        yield 'getMultiple' => [static fn(SimpleCache $cache): mixed => $cache->getMultiple(['user.1', 'user{42}'])];
        yield 'setMultiple' => [static fn(SimpleCache $cache): mixed => $cache->setMultiple(['user.1' => 1, '' => 2])];
        yield 'deleteMultiple' => [static fn(SimpleCache $cache): mixed => $cache->deleteMultiple(['user/42'])];
        yield 'getMultiple with an integer key' => [
            static fn(SimpleCache $cache): mixed => $cache->getMultiple([123]),
        ];
        yield 'deleteMultiple with an integer key' => [
            static fn(SimpleCache $cache): mixed => $cache->deleteMultiple([123]),
        ];
        yield 'getMultiple with a key that is not a string' => [
            static fn(SimpleCache $cache): mixed => $cache->getMultiple(['user.1', 1.5]),
        ];
        yield 'setMultiple with a key that is not a string' => [
            static fn(SimpleCache $cache): mixed => $cache->setMultiple(
                (static function (): Generator {
                    yield null => 1;
                })(),
            ),
        ];
        yield 'deleteMultiple with a key that is not a string' => [
            static fn(SimpleCache $cache): mixed => $cache->deleteMultiple([true]),
        ];
    }

    /**
     * @param callable(SimpleCache): mixed $operation
     */
    #[Test]
    #[DataProvider('operationsWithAnInvalidKey')]
    public function it_refuses_an_invalid_key_without_touching_the_store(callable $operation): void
    {
        try {
            $operation($this->cache);
            self::fail('An invalid key was accepted.');
        } catch (SimpleCacheInvalidArgumentException $exception) {
            self::assertInstanceOf(InvalidCacheKeyException::class, $exception);
            self::assertSame([], $this->store->calls);
        }
    }

    #[Test]
    public function it_preserves_numeric_string_keys_in_bulk_results(): void
    {
        $this->cache->setMultiple(['123' => 'stored', '0' => null]);
        $keys = [];
        $values = [];

        // @mago-expect analysis:mixed-assignment Cache values can have any type
        foreach ($this->cache->getMultiple(['123', '0', '001', '123'], 'missing') as $key => $value) {
            $keys[] = $key;
            $values[] = $value;
        }

        self::assertSame(['123', '0', '001'], $keys);
        self::assertSame(['stored', null, 'missing'], $values);
    }

    /**
     * @param list<string> $keys
     *
     * @return Generator<int, string>
     */
    private static function generate(array $keys): Generator
    {
        yield from $keys;
    }
}
