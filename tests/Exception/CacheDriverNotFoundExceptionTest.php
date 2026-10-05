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
use Dirthara\Cache\Exception\CacheDriverNotFoundException;

#[CoversClass(CacheDriverNotFoundException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class CacheDriverNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new CacheDriverNotFoundException();

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
        $exception = new CacheDriverNotFoundException('message', 3, $previous, ['driver' => 'Missing']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['driver' => 'Missing'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new CacheDriverNotFoundException(context: ['driver' => 'Missing', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['driver' => 'Replaced', 'cache' => 'default']));
        self::assertSame(['driver' => 'Replaced', 'kept' => true, 'cache' => 'default'], $exception->context);
    }

    #[Test]
    public function it_describes_a_driver_name_without_a_driver(): void
    {
        $exception = CacheDriverNotFoundException::for("class@anonymous\0/app/src/Store.php:3$0");

        self::assertSame(
            'Unable to find the cache driver "class@anonymous\\000/app/src/Store.php:3$0": no driver is registered under that name.',
            $exception->getMessage(),
        );
        self::assertSame(['driver' => 'class@anonymous\\000/app/src/Store.php:3$0'], $exception->context);
    }
}
