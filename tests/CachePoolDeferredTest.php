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

use function array_keys;
use function gc_collect_cycles;

use stdClass;

#[CoversClass(CachePool::class)]
#[UsesClass(CacheItem::class)]
#[UsesClass(StoredValue::class)]
#[UsesClass(MemoryCacheStore::class)]
#[UsesClass(NativeCacheSerialiser::class)]
#[UsesClass(InvalidCacheKeyException::class)]
#[UsesClass(CacheSerialisationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class CachePoolDeferredTest extends TestCase
{
    #[Test]
    public function it_does_not_store_a_deferred_item_until_it_commits(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);

        self::assertTrue($pool->saveDeferred($pool->getItem('user.42')->set('Ada')));
        self::assertNull($store->inner->get('user.42'));

        self::assertTrue($pool->commit());
        self::assertNotNull($store->inner->get('user.42'));
    }

    #[Test]
    public function it_hits_a_deferred_item_before_it_commits(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->saveDeferred($pool->getItem('user.42')->set('Ada'));

        self::assertSame('Ada', $pool->getItem('user.42')->get());
        self::assertTrue($pool->hasItem('user.42'));
        self::assertSame('Ada', $pool->getItems(['user.42'])['user.42']->get());
        self::assertSame(['get'], $store->operations());
    }

    #[Test]
    public function it_keeps_the_value_a_deferred_item_had_when_it_was_deferred(): void
    {
        $pool = $this->pool(new RecordingCacheStore());
        $user = new stdClass();
        $user->name = 'Ada';
        $item = $pool->getItem('user.42')->set($user);
        $pool->saveDeferred($item);

        $user->name = 'Grace';
        $item->set('changed');

        self::assertEquals((object) ['name' => 'Ada'], $pool->getItem('user.42')->get());
    }

    #[Test]
    public function it_commits_every_deferred_item_at_once(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->saveDeferred($pool->getItem('first')->set(1));
        $pool->saveDeferred($pool->getItem('second')->set(2));
        $pool->saveDeferred($pool->getItem('first')->set(3));

        $pool->commit();

        self::assertSame(['get', 'get', 'putMultiple'], $store->operations());
        self::assertSame(['first', 'second'], array_keys($store->inner->getMultiple(['first', 'second'])));
        self::assertSame([3, 2], [$pool->getItem('first')->get(), $pool->getItem('second')->get()]);
    }

    #[Test]
    public function it_commits_nothing_when_nothing_is_deferred(): void
    {
        $store = new RecordingCacheStore();

        self::assertTrue($this->pool($store)->commit());
        self::assertSame([], $store->calls);
    }

    #[Test]
    public function it_forgets_the_deferred_items_once_it_commits(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->saveDeferred($pool->getItem('user.42')->set('Ada'));
        $pool->commit();

        $pool->commit();

        self::assertSame(['get', 'putMultiple'], $store->operations());
    }

    #[Test]
    public function it_deletes_the_deferred_items_that_expired_before_it_commits(): void
    {
        $store = new RecordingCacheStore();
        $clock = new TestClock('2026-10-05 12:00:00');
        $pool = $this->pool($store, $clock);
        $pool->save($pool->getItem('42')->set('stored'));
        $pool->saveDeferred($pool->getItem('42')->set('Ada')->expiresAfter(10));
        $pool->saveDeferred($pool->getItem('kept')->set('kept')->expiresAfter(60));
        $clock->advance('+10 seconds');

        self::assertFalse($pool->hasItem('42'));
        self::assertTrue($pool->commit());

        self::assertContains(['deleteMultiple', ['42']], $store->calls);
        self::assertNull($store->inner->get('42'));
        self::assertTrue($pool->hasItem('kept'));
    }

    #[Test]
    public function it_reports_a_commit_the_store_fails(): void
    {
        $store = new RecordingCacheStore();
        $clock = new TestClock();
        $pool = $this->pool($store, $clock);
        $pool->saveDeferred($pool->getItem('saved')->set('saved'));
        $store->failing = true;

        self::assertFalse($pool->commit());

        $pool->saveDeferred($pool->getItem('expired')->set('expired')->expiresAfter(0));

        self::assertFalse($pool->commit());
    }

    #[Test]
    public function it_traps_the_exceptions_of_a_store_that_is_unavailable_while_committing(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->saveDeferred($pool->getItem('user.42')->set('Ada'));
        $store->throwing = true;

        self::assertFalse($pool->commit());
    }

    #[Test]
    public function it_refuses_to_defer_a_value_that_cannot_be_serialised(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);

        self::assertFalse($pool->saveDeferred($pool->getItem('user.42')->set(static fn(): string => 'Ada')));
        self::assertTrue($pool->commit());
        self::assertSame(['get'], $store->operations());
    }

    #[Test]
    public function it_replaces_a_deferred_item_with_one_that_is_saved(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->saveDeferred($pool->getItem('user.42')->set('Ada'));

        $pool->save($pool->getItem('user.42')->set('Grace'));
        $pool->commit();

        self::assertSame('Grace', $pool->getItem('user.42')->get());
        self::assertNotContains('putMultiple', $store->operations());
    }

    #[Test]
    public function it_forgets_a_deferred_item_that_is_deleted(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->saveDeferred($pool->getItem('first')->set(1));
        $pool->saveDeferred($pool->getItem('second')->set(2));

        $pool->deleteItem('first');
        $pool->deleteItems(['second']);

        self::assertTrue($pool->commit());
        self::assertSame([], $store->inner->getMultiple(['first', 'second']));
        self::assertNotContains('putMultiple', $store->operations());
    }

    #[Test]
    public function it_forgets_the_deferred_items_when_it_clears(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->saveDeferred($pool->getItem('user.42')->set('Ada'));

        $pool->clear();
        $pool->commit();

        self::assertNull($store->inner->get('user.42'));
    }

    #[Test]
    public function it_commits_the_deferred_items_when_it_is_destroyed(): void
    {
        $store = new RecordingCacheStore();
        $pool = $this->pool($store);
        $pool->saveDeferred($pool->getItem('user.42')->set('Ada'));

        unset($pool);
        gc_collect_cycles();

        self::assertNotNull($store->inner->get('user.42'));
    }

    private function pool(RecordingCacheStore $store, ?TestClock $clock = null): CachePool
    {
        return new CachePool($store, new NativeCacheSerialiser(), $clock ?? new TestClock());
    }
}
