<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Exception;

use stdClass;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesTrait;
use Dirthara\Cache\Exception\CacheException;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Exception\InvalidCacheKeyException;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

#[CoversClass(InvalidCacheKeyException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class InvalidCacheKeyExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new InvalidCacheKeyException();

        self::assertInstanceOf(CacheException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertInstanceOf(CacheInvalidArgumentException::class, $exception);
        self::assertInstanceOf(SimpleCacheInvalidArgumentException::class, $exception);
        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_keeps_a_previous_exception_and_its_context(): void
    {
        $previous = new InvalidArgumentException('cause');
        $exception = new InvalidCacheKeyException('message', 3, $previous, ['key' => 'user:42']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['key' => 'user:42'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new InvalidCacheKeyException(context: ['key' => 'user:42', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['key' => 'Replaced', 'pool' => 'default']));
        self::assertSame(['key' => 'Replaced', 'kept' => true, 'pool' => 'default'], $exception->context);
    }

    #[Test]
    public function it_describes_a_key_that_is_not_a_string(): void
    {
        $exception = InvalidCacheKeyException::notAString(new stdClass());

        self::assertSame(
            'Unable to use a cache key of type stdClass: a key has to be a string.',
            $exception->getMessage(),
        );
        self::assertSame(['type' => 'stdClass'], $exception->context);
    }

    #[Test]
    public function it_describes_an_empty_key(): void
    {
        $exception = InvalidCacheKeyException::empty();

        self::assertSame(
            'Unable to use an empty cache key: a key needs at least one character.',
            $exception->getMessage(),
        );
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_describes_a_key_with_a_reserved_character(): void
    {
        $exception = InvalidCacheKeyException::reservedCharacters("user:42\n", '{}()/\\@:');

        self::assertSame(
            'Unable to use the cache key "user:42\\n": a key cannot contain any of the reserved characters {}()/\\@:.',
            $exception->getMessage(),
        );
        self::assertSame(['key' => 'user:42\\n'], $exception->context);
    }
}
