<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests;

use function iterator_to_array;

use Dirthara\Cache\CacheItem;
use Dirthara\Cache\CachePool;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Cache\ValueObject\StoredValue;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use Dirthara\Cache\Contract\CacheSerialiser;
use Dirthara\Cache\Tests\Fixtures\TestClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;
use Dirthara\Cache\Exception\InvalidCacheKeyException;
use Dirthara\Cache\Tests\Fixtures\RecordingCacheStore;
use Dirthara\Cache\Exception\CacheSerialisationException;

use function array_map;
use function array_keys;
use function array_values;

use stdClass;
use DateTimeImmutable;
use Psr\Cache\CacheItemInterface;

#[CoversClass(CachePool::class)]
#[UsesClass(CacheItem::class)]
#[UsesClass(StoredValue::class)]
#[UsesClass(MemoryCacheStore::class)]
#[UsesClass(NativeCacheSerialiser::class)]
#[UsesClass(InvalidCacheKeyException::class)]
#[UsesClass(CacheSerialisationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class CachePoolTest extends TestCase
{
    #[Test]
    public function it_misses_a_key_that_was_never_saved(): void
    {
        $item = $this->pool(new RecordingCacheStore())->getItem('user.42');

        self::assertSame('user.42', $item->getKey());
        self::assertFalse($item->isHit());
        self::assertNull($item->get());
    }

    #[Test]
    public function it_hits_a_saved_item(): void
    {
        $pool = $this->pool(new RecordingCacheStore());

        self::assertTrue($pool->save($pool->getItem('user.42')->set(['name' => 'Ada'])));

        $item = $pool->getItem('user.42');
        self::assertTrue($item->isHit());
        self::assertSame(['name' => 'Ada'], $item->get());
        self::assertTrue($pool->hasItem('user.42'));
    }

    #[Test]
    public function it_hits_a_saved_null(): void
    {
        $pool = $this->pool(new RecordingCacheStore());
        $pool->save($pool->getItem('user.42')->set(null));

        self::assertTrue($pool->getItem('user.42')->isHit());
        self::assertTrue($pool->hasItem('user.42'));
    }

    #[Test]
    public function it_stores_a_copy_of_an_object_rather_than_the_object_itself(): void
    {
        $pool = $this->pool(new RecordingCacheStore());
        $user = new stdClass();
        $user->name = 'Ada';
        $pool->save($pool->getItem('user.42')->set($user));

        $user->name = 'Grace';

        self::assertEquals((object) ['name' => 'Ada'], $pool->getItem('user.42')->get());
    }

    #[Test]
    public function it_has_no_item_for_a_key_that_was_never_saved(): void
    {
        self::assertFalse($this->pool(new RecordingCacheStore())->hasItem('user.42'));
    }

    #[Test]
    public function it_stores_the_expiry_of_an_item(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store, new TestClock('2026-10-05 12:00:00'));

        $pool->save($pool->getItem('user.42')->set('Ada')->expiresAfter(60));

        self::assertEquals(new DateTimeImmutable('2026-10-05 12:01:00'), $store->inner->get('user.42')?->expiresAt);
    }

    #[Test]
    public function it_hits_an_item_until_it_expires(): void
    {
        $clock = new TestClock('2026-10-05 12:00:00');
        $pool = $this->pool(new RecordingCacheStore(), $clock);
        $pool->save($pool->getItem('user.42')->set('Ada')->expiresAfter(60));

        $clock->advance('+59 seconds');
        self::assertTrue($pool->hasItem('user.42'));

        $clock->advance('+1 second');
        self::assertFalse($pool->hasItem('user.42'));
        self::assertNull($pool->getItem('user.42')->get());
    }

    #[Test]
    public function it_deletes_an_item_saved_with_an_expiry_that_has_passed(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->save($pool->getItem('user.42')->set('Ada'));

        self::assertTrue($pool->save($pool->getItem('user.42')->set('Grace')->expiresAfter(0)));

        self::assertNull($store->inner->get('user.42'));
        self::assertSame(['get', 'put', 'get', 'delete'], $store->operations());
    }

    #[Test]
    public function it_stores_an_item_without_an_expiry_permanently_even_when_it_had_one(): void
    {
        $clock = new TestClock('2026-10-05 12:00:00');
        $pool = $this->pool(new RecordingCacheStore(), $clock);
        $pool->save($pool->getItem('user.42')->set('Ada')->expiresAfter(60));

        $pool->save($pool->getItem('user.42')->set('Grace'));
        $clock->advance('+1 year');

        self::assertSame('Grace', $pool->getItem('user.42')->get());
    }

    #[Test]
    public function it_refuses_to_save_an_item_it_did_not_create(): void
    {
        $store = new RecordingCacheStore();
        $item = self::createStub(CacheItemInterface::class);

        self::assertFalse($this->pool($store)->save($item));
        self::assertFalse($this->pool($store)->saveDeferred($item));
        self::assertSame([], $store->calls);
    }

    #[Test]
    public function it_fails_to_save_a_value_that_cannot_be_serialised_and_keeps_the_cached_one(): void
    {
        $pool = $this->pool(new RecordingCacheStore());
        $pool->save($pool->getItem('user.42')->set('Ada'));

        self::assertFalse($pool->save($pool->getItem('user.42')->set(static fn(): string => 'Grace')));
        self::assertSame('Ada', $pool->getItem('user.42')->get());
    }

    #[Test]
    public function it_misses_a_payload_it_cannot_deserialise(): void
    {
        $store = new RecordingCacheStore();
        $store->inner->put('user.42', new StoredValue('not a serialised value', null));

        self::assertFalse($this->pool($store)->getItem('user.42')->isHit());
    }

    #[Test]
    public function it_returns_the_items_for_several_keys_in_the_order_asked_for(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->save($pool->getItem('third')->set(3));
        $pool->save($pool->getItem('first')->set(1));

        $items = iterator_to_array($pool->getItems(['first', 'second', 'third', 'first']));

        self::assertSame(['first', 'second', 'third'], array_keys($items));
        self::assertSame(
            [1, null, 3],
            array_map(static fn(CacheItemInterface $item): mixed => $item->get(), array_values($items)),
        );
        self::assertSame(
            [true, false, true],
            array_map(static fn(CacheItemInterface $item): bool => $item->isHit(), array_values($items)),
        );
        self::assertSame(['getMultiple', ['first', 'second', 'third']], $store->calls[4]);
    }

    #[Test]
    public function it_returns_no_items_for_no_keys_without_asking_the_store(): void
    {
        $store = new RecordingCacheStore();

        self::assertSame([], iterator_to_array($this->pool($store)->getItems()));
        self::assertSame([], $store->calls);
    }

    #[Test]
    public function it_misses_the_items_that_have_expired_among_several(): void
    {
        $clock = new TestClock('2026-10-05 12:00:00');
        $pool = $this->pool(new RecordingCacheStore(), $clock);
        $pool->save($pool->getItem('short')->set('short')->expiresAfter(10));
        $pool->save($pool->getItem('long')->set('long')->expiresAfter(60));
        $clock->advance('+30 seconds');

        $items = iterator_to_array($pool->getItems(['short', 'long']));

        self::assertFalse($items['short']->isHit());
        self::assertTrue($items['long']->isHit());
    }

    #[Test]
    public function it_deletes_an_item(): void
    {
        $pool = $this->pool(new RecordingCacheStore());
        $pool->save($pool->getItem('user.42')->set('Ada'));

        self::assertTrue($pool->deleteItem('user.42'));
        self::assertFalse($pool->hasItem('user.42'));
    }

    #[Test]
    public function it_succeeds_in_deleting_an_item_that_does_not_exist(): void
    {
        self::assertTrue($this->pool(new RecordingCacheStore())->deleteItem('user.42'));
    }

    #[Test]
    public function it_deletes_several_items_at_once(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->save($pool->getItem('first')->set(1));
        $pool->save($pool->getItem('second')->set(2));
        $pool->save($pool->getItem('kept')->set(3));

        self::assertTrue($pool->deleteItems(['first', 'second', 'first']));

        self::assertFalse($pool->hasItem('first'));
        self::assertFalse($pool->hasItem('second'));
        self::assertTrue($pool->hasItem('kept'));
        self::assertContains(['deleteMultiple', ['first', 'second']], $store->calls);
    }

    #[Test]
    public function it_clears_every_item(): void
    {
        $pool = $this->pool(new RecordingCacheStore());
        $pool->save($pool->getItem('first')->set(1));
        $pool->save($pool->getItem('second')->set(2));

        self::assertTrue($pool->clear());

        self::assertFalse($pool->hasItem('first'));
        self::assertFalse($pool->hasItem('second'));
    }

    #[Test]
    public function it_reports_each_operation_the_store_fails(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $store->failing = true;

        self::assertFalse($pool->save($pool->getItem('user.42')->set('Ada')));
        self::assertFalse($pool->save($pool->getItem('user.42')->set('Ada')->expiresAfter(0)));
        self::assertFalse($pool->deleteItem('user.42'));
        self::assertFalse($pool->deleteItems(['user.42']));
        self::assertFalse($pool->clear());
    }

    #[Test]
    public function it_traps_the_exceptions_of_a_store_that_is_unavailable(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $store->throwing = true;

        self::assertFalse($pool->getItem('user.42')->isHit());
        self::assertFalse(iterator_to_array($pool->getItems(['user.42']))['user.42']->isHit());
        self::assertFalse($pool->hasItem('user.42'));
        self::assertFalse($pool->save($pool->getItem('user.42')->set('Ada')));
        self::assertFalse($pool->deleteItem('user.42'));
        self::assertFalse($pool->deleteItems(['user.42']));
        self::assertFalse($pool->clear());
    }

    /** @return iterable<string, array{string}> */
    public static function unrestorablePayloads(): iterable
    {
        yield 'missing class' => ['O:11:"App\\Removed":0:{}'];
        yield 'nested missing class' => ['a:1:{i:0;O:11:"App\\Removed":0:{}}'];
        yield 'invalid native object state' => ['O:17:"DateTimeImmutable":1:{s:4:"date";i:1;}'];
    }

    #[Test]
    #[DataProvider('unrestorablePayloads')]
    public function it_treats_unrestorable_values_as_misses(string $payload): void
    {
        $store = new RecordingCacheStore();
        $store->inner->put('key', new StoredValue($payload, null));
        $pool = $this->pool($store);

        self::assertFalse($pool->getItem('key')->isHit());
        self::assertNull($pool->getItem('key')->get());
    }

    #[Test]
    public function it_deletes_an_item_that_expires_while_its_value_is_serialised(): void
    {
        $store = new RecordingCacheStore();
        $clock = new TestClock();
        $store->inner->put('key', new StoredValue('s:3:"old";', null));
        $serialiser = self::createStub(CacheSerialiser::class);
        $serialiser
            ->method('serialise')
            ->willReturnCallback(static function (mixed $value) use ($clock): string {
                $clock->advance('+2 seconds');

                return new NativeCacheSerialiser()->serialise($value);
            });
        $pool = new CachePool($store, $serialiser, $clock);
        $item = CacheItem::miss('key', $clock)->set('new')->expiresAfter(1);

        self::assertTrue($pool->save($item));
        self::assertNull($store->inner->get('key'));
        self::assertSame(['delete'], $store->operations());
    }

    private function pool(RecordingCacheStore $store, ?TestClock $clock = null): CachePool
    {
        return new CachePool($store, new NativeCacheSerialiser(), $clock ?? new TestClock());
    }
}
