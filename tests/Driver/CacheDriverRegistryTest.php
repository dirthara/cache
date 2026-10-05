<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Driver;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Cache\Driver\CacheDriverRegistry;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Tests\Fixtures\RecordingCacheDriver;
use Dirthara\Cache\Exception\CacheDriverNotFoundException;
use Dirthara\Cache\Exception\DuplicateCacheDriverException;

#[CoversClass(CacheDriverRegistry::class)]
#[UsesClass(CacheDriverNotFoundException::class)]
#[UsesClass(DuplicateCacheDriverException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class CacheDriverRegistryTest extends TestCase
{
    #[Test]
    public function it_provides_the_driver_registered_under_a_name(): void
    {
        $registry = new CacheDriverRegistry();
        $memory = new RecordingCacheDriver();
        $redis = new RecordingCacheDriver();

        $registry->register('memory', $memory);
        $registry->register('redis', $redis);

        self::assertSame($memory, $registry->driver('memory'));
        self::assertSame($redis, $registry->driver('redis'));
    }

    #[Test]
    public function it_has_no_drivers_until_one_is_registered(): void
    {
        self::assertFalse(new CacheDriverRegistry()->has('memory'));
    }

    #[Test]
    public function it_has_each_driver_registered_under_a_name(): void
    {
        $registry = new CacheDriverRegistry();
        $registry->register('memory', new RecordingCacheDriver());
        $registry->register('redis', new RecordingCacheDriver());

        self::assertTrue($registry->has('memory'));
        self::assertTrue($registry->has('redis'));
        self::assertFalse($registry->has('database'));
    }

    #[Test]
    public function it_matches_driver_names_exactly_when_asked_whether_it_has_one(): void
    {
        $registry = new CacheDriverRegistry();
        $registry->register('memory', new RecordingCacheDriver());

        self::assertFalse($registry->has('Memory'));
        self::assertFalse($registry->has(' memory'));
        self::assertFalse($registry->has(''));
    }

    #[Test]
    public function it_rejects_a_second_driver_under_the_same_name(): void
    {
        $registry = new CacheDriverRegistry();
        $first = new RecordingCacheDriver();
        $registry->register('memory', $first);

        try {
            $registry->register('memory', new RecordingCacheDriver());
            self::fail('A second driver under the same name was accepted.');
        } catch (DuplicateCacheDriverException $exception) {
            self::assertSame(['driver' => 'memory'], $exception->context);
        }

        self::assertTrue($registry->has('memory'));
        self::assertSame($first, $registry->driver('memory'));
    }

    #[Test]
    public function it_refuses_a_driver_name_without_a_driver(): void
    {
        $registry = new CacheDriverRegistry();
        $registry->register('memory', new RecordingCacheDriver());

        try {
            $registry->driver('redis');
            self::fail('A driver name without a driver was accepted.');
        } catch (CacheDriverNotFoundException $exception) {
            self::assertSame(['driver' => 'redis'], $exception->context);
        }
    }

    #[Test]
    public function it_matches_driver_names_exactly(): void
    {
        $registry = new CacheDriverRegistry();
        $registry->register('memory', new RecordingCacheDriver());

        $this->expectException(CacheDriverNotFoundException::class);

        $registry->driver('Memory');
    }
}
