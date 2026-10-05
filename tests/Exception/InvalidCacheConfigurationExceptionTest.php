<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Exception;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesTrait;
use Dirthara\Cache\Exception\CacheException;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Exception\InvalidCacheConfigurationException;

#[CoversClass(InvalidCacheConfigurationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class InvalidCacheConfigurationExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new InvalidCacheConfigurationException();

        self::assertInstanceOf(CacheException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_keeps_a_previous_exception_and_its_context(): void
    {
        $previous = new InvalidArgumentException('cause');
        $exception = new InvalidCacheConfigurationException('message', 3, $previous, ['driver' => 'Missing']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['driver' => 'Missing'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new InvalidCacheConfigurationException(context: ['driver' => 'Missing', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['driver' => 'Replaced', 'cache' => 'default']));
        self::assertSame(['driver' => 'Replaced', 'kept' => true, 'cache' => 'default'], $exception->context);
    }

    #[Test]
    public function it_describes_a_missing_option(): void
    {
        $exception = InvalidCacheConfigurationException::missingOption(
            "class@anonymous\0/app/src/Job.php:3$0",
            "pass\nword",
        );

        self::assertSame(
            'Unable to configure the "class@anonymous\\000/app/src/Job.php:3$0" cache: the option "pass\\nword" is required and has no default.',
            $exception->getMessage(),
        );
        self::assertSame(
            ['driver' => 'class@anonymous\\000/app/src/Job.php:3$0', 'option' => 'pass\\nword'],
            $exception->context,
        );
    }

    #[Test]
    public function it_describes_an_option_of_the_wrong_type(): void
    {
        $exception = InvalidCacheConfigurationException::invalidOptionType(
            "class@anonymous\0/app/src/Job.php:3$0",
            "pass\nword",
            'string',
            42,
        );

        self::assertSame(
            'Unable to configure the "class@anonymous\\000/app/src/Job.php:3$0" cache: the option "pass\\nword" has to be of type string, int given.',
            $exception->getMessage(),
        );
        self::assertSame(
            [
                'driver' => 'class@anonymous\\000/app/src/Job.php:3$0',
                'option' => 'pass\\nword',
                'expected' => 'string',
                'actual' => 'int',
            ],
            $exception->context,
        );
    }
}
