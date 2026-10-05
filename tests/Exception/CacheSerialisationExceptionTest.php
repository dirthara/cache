<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Exception;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesTrait;
use Dirthara\Cache\Exception\CacheException;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Exception\CacheSerialisationException;

#[CoversClass(CacheSerialisationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class CacheSerialisationExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new CacheSerialisationException();

        self::assertInstanceOf(CacheException::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_keeps_a_previous_exception_and_its_context(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new CacheSerialisationException('message', 3, $previous, ['type' => 'Closure']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['type' => 'Closure'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new CacheSerialisationException(context: ['type' => 'Closure', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['type' => 'Replaced', 'key' => 'user.42']));
        self::assertSame(['type' => 'Replaced', 'kept' => true, 'key' => 'user.42'], $exception->context);
    }

    #[Test]
    public function it_describes_a_value_that_cannot_be_serialised(): void
    {
        $previous = new RuntimeException('cause');
        $exception = CacheSerialisationException::unableToSerialise(
            "class@anonymous\0/app/src/Store.php:3$0",
            $previous,
        );

        self::assertSame(
            'Unable to serialise a value of type "class@anonymous\\000/app/src/Store.php:3$0" for the cache.',
            $exception->getMessage(),
        );
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['type' => 'class@anonymous\\000/app/src/Store.php:3$0'], $exception->context);
    }

    #[Test]
    public function it_describes_a_malformed_payload(): void
    {
        $previous = new RuntimeException('cause');
        $exception = CacheSerialisationException::unableToDeserialise($previous);

        self::assertSame('Unable to deserialise a cached payload: the payload is malformed.', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_describes_a_payload_of_a_class_that_does_not_exist(): void
    {
        $exception = CacheSerialisationException::unknownClass();

        self::assertSame(
            'Unable to deserialise a cached payload: it holds an object of a class that does not exist.',
            $exception->getMessage(),
        );
        self::assertNull($exception->getPrevious());
    }
}
