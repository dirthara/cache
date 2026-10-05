<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests;

use DateTime;
use DateInterval;
use DateTimeZone;
use DateTimeImmutable;
use Dirthara\Cache\CacheItem;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Cache\Tests\Fixtures\TestClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

#[CoversClass(CacheItem::class)]
final class CacheItemTest extends TestCase
{
    #[Test]
    public function it_carries_its_key(): void
    {
        self::assertSame('user.42', CacheItem::miss('user.42', new TestClock())->getKey());
        self::assertSame('user.42', CacheItem::hit('user.42', 'Ada', new TestClock())->getKey());
    }

    #[Test]
    public function it_returns_the_value_of_a_hit(): void
    {
        $item = CacheItem::hit('user.42', 'Ada', new TestClock());

        self::assertTrue($item->isHit());
        self::assertSame('Ada', $item->get());
        self::assertSame('Ada', $item->value);
    }

    #[Test]
    public function it_can_be_a_hit_holding_null(): void
    {
        $item = CacheItem::hit('user.42', null, new TestClock());

        self::assertTrue($item->isHit());
        self::assertNull($item->get());
    }

    #[Test]
    public function it_has_no_value_on_a_miss(): void
    {
        $item = CacheItem::miss('user.42', new TestClock());

        self::assertFalse($item->isHit());
        self::assertNull($item->get());
        self::assertNull($item->value);
    }

    #[Test]
    public function it_keeps_returning_null_from_a_miss_after_a_value_is_set(): void
    {
        $item = CacheItem::miss('user.42', new TestClock());

        self::assertSame($item, $item->set('Ada'));
        self::assertFalse($item->isHit());
        self::assertNull($item->get());
        self::assertSame('Ada', $item->value);
    }

    #[Test]
    public function it_returns_a_value_set_on_a_hit(): void
    {
        $item = CacheItem::hit('user.42', 'Ada', new TestClock());

        $item->set('Grace');

        self::assertTrue($item->isHit());
        self::assertSame('Grace', $item->get());
    }

    #[Test]
    public function it_never_expires_until_told_to(): void
    {
        self::assertNull(CacheItem::miss('user.42', new TestClock())->expiry);
        self::assertNull(CacheItem::hit('user.42', 'Ada', new TestClock())->expiry);
    }

    #[Test]
    public function it_expires_at_a_moment(): void
    {
        $item = CacheItem::miss('user.42', new TestClock());
        $moment = new DateTimeImmutable('2026-10-06 08:30:00.125', new DateTimeZone('Europe/Amsterdam'));

        self::assertSame($item, $item->expiresAt($moment));
        self::assertEquals($moment, $item->expiry);
    }

    #[Test]
    public function it_takes_a_snapshot_of_a_mutable_moment(): void
    {
        $item = CacheItem::miss('user.42', new TestClock());
        $moment = new DateTime('2026-10-06 08:30:00');

        $item->expiresAt($moment);
        $moment->modify('+1 day');

        self::assertEquals(new DateTimeImmutable('2026-10-06 08:30:00'), $item->expiry);
    }

    #[Test]
    public function it_stops_expiring_when_given_no_moment(): void
    {
        $item = CacheItem::miss('user.42', new TestClock());
        $item->expiresAt(new DateTimeImmutable('2026-10-06 08:30:00'));

        $item->expiresAt(null);

        self::assertNull($item->expiry);
    }

    #[Test]
    public function it_expires_a_number_of_seconds_from_now(): void
    {
        $item = CacheItem::miss('user.42', new TestClock('2026-10-05 12:00:00.250'));

        self::assertSame($item, $item->expiresAfter(90));
        self::assertEquals(new DateTimeImmutable('2026-10-05 12:01:30.250'), $item->expiry);
    }

    #[Test]
    public function it_expires_an_interval_from_now(): void
    {
        $item = CacheItem::miss('user.42', new TestClock('2026-10-05 12:00:00.250'));

        $item->expiresAfter(new DateInterval('P1DT2H'));

        self::assertEquals(new DateTimeImmutable('2026-10-06 14:00:00.250'), $item->expiry);
    }

    #[Test]
    public function it_counts_from_the_moment_the_expiry_is_set(): void
    {
        $clock = new TestClock('2026-10-05 12:00:00');
        $item = CacheItem::miss('user.42', $clock);
        $clock->advance('+1 hour');

        $item->expiresAfter(60);

        self::assertEquals(new DateTimeImmutable('2026-10-05 13:01:00'), $item->expiry);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function secondsThatAreNotInTheFuture(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-60];
        yield 'the smallest integer' => [PHP_INT_MIN];
    }

    #[Test]
    #[DataProvider('secondsThatAreNotInTheFuture')]
    public function it_expires_now_for_seconds_that_are_not_in_the_future(int $seconds): void
    {
        $clock = new TestClock();
        $item = CacheItem::miss('user.42', $clock);

        $item->expiresAfter($seconds);

        self::assertEquals($clock->now(), $item->expiry);
    }

    #[Test]
    public function it_expires_in_the_past_for_a_negative_interval(): void
    {
        $item = CacheItem::miss('user.42', new TestClock('2026-10-05 12:00:00'));

        $item->expiresAfter(DateInterval::createFromDateString('-1 minute'));

        self::assertEquals(new DateTimeImmutable('2026-10-05 11:59:00'), $item->expiry);
    }

    #[Test]
    public function it_expires_far_in_the_future_for_a_large_number_of_seconds(): void
    {
        $clock = new TestClock('2026-10-05 12:00:00');
        $item = CacheItem::miss('user.42', $clock);
        $seconds = PHP_INT_MAX - $clock->now()->getTimestamp();

        $item->expiresAfter($seconds);

        self::assertSame(PHP_INT_MAX, $item->expiry?->getTimestamp());
    }

    #[Test]
    public function it_never_expires_for_more_seconds_than_a_moment_can_hold(): void
    {
        $item = CacheItem::miss('user.42', new TestClock());
        $item->expiresAfter(60);

        $item->expiresAfter(PHP_INT_MAX);

        self::assertNull($item->expiry);
    }

    #[Test]
    public function it_stops_expiring_when_given_no_time(): void
    {
        $item = CacheItem::miss('user.42', new TestClock());
        $item->expiresAfter(60);

        $item->expiresAfter(null);

        self::assertNull($item->expiry);
    }
}
