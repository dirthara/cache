<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Driver\Memory;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Cache\ValueObject\StoredValue;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Cache\Driver\Memory\MemoryCacheStore;

#[CoversClass(MemoryCacheStore::class)]
#[UsesClass(StoredValue::class)]
final class MemoryCacheStoreTest extends TestCase
{
    #[Test]
    public function it_has_nothing_under_a_key_that_was_never_put(): void
    {
        self::assertNull(new MemoryCacheStore()->get('user.42'));
    }

    #[Test]
    public function it_returns_the_value_put_under_a_key(): void
    {
        $store = new MemoryCacheStore();
        $value = new StoredValue('payload', new DateTimeImmutable('2026-10-05 12:00:00'));

        self::assertTrue($store->put('user.42', $value));
        self::assertSame($value, $store->get('user.42'));
    }

    #[Test]
    public function it_replaces_the_value_under_a_key(): void
    {
        $store = new MemoryCacheStore();
        $replacement = new StoredValue('second', null);
        $store->put('user.42', new StoredValue('first', null));

        $store->put('user.42', $replacement);

        self::assertSame($replacement, $store->get('user.42'));
    }

    #[Test]
    public function it_keeps_an_expired_value_because_expiry_is_up_to_the_pool(): void
    {
        $store = new MemoryCacheStore();
        $expired = new StoredValue('payload', new DateTimeImmutable('2000-01-01 00:00:00'));

        $store->put('user.42', $expired);

        self::assertSame($expired, $store->get('user.42'));
    }

    #[Test]
    public function it_returns_only_the_keys_it_has_in_the_order_they_were_asked_for(): void
    {
        $store = new MemoryCacheStore();
        $first = new StoredValue('first', null);
        $third = new StoredValue('third', null);
        $store->put('third', $third);
        $store->put('first', $first);

        self::assertSame(['first' => $first, 'third' => $third], $store->getMultiple(['first', 'second', 'third']));
        self::assertSame([], $store->getMultiple([]));
    }

    #[Test]
    public function it_puts_several_values_at_once(): void
    {
        $store = new MemoryCacheStore();
        $first = new StoredValue('first', null);
        $second = new StoredValue('second', null);

        self::assertTrue($store->putMultiple(['first' => $first, 'second' => $second]));
        self::assertSame(['first' => $first, 'second' => $second], $store->getMultiple(['first', 'second']));
    }

    #[Test]
    public function it_deletes_a_key(): void
    {
        $store = new MemoryCacheStore();
        $store->put('user.42', new StoredValue('payload', null));

        self::assertTrue($store->delete('user.42'));
        self::assertNull($store->get('user.42'));
    }

    #[Test]
    public function it_succeeds_in_deleting_a_key_it_does_not_have(): void
    {
        self::assertTrue(new MemoryCacheStore()->delete('user.42'));
    }

    #[Test]
    public function it_deletes_several_keys_at_once_and_keeps_the_others(): void
    {
        $store = new MemoryCacheStore();
        $kept = new StoredValue('kept', null);
        $store->putMultiple([
            'first' => new StoredValue('first', null),
            'second' => new StoredValue('second', null),
            'kept' => $kept,
        ]);

        self::assertTrue($store->deleteMultiple(['first', 'second', 'missing']));
        self::assertSame(['kept' => $kept], $store->getMultiple(['first', 'second', 'kept']));
    }

    #[Test]
    public function it_clears_every_key(): void
    {
        $store = new MemoryCacheStore();
        $store->putMultiple(['first' => new StoredValue('first', null), 'second' => new StoredValue('second', null)]);

        self::assertTrue($store->clear());
        self::assertSame([], $store->getMultiple(['first', 'second']));
    }
}
