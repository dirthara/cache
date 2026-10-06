<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Serialiser;

use Error;
use stdClass;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Cache\Exception\HasExceptionContext;
use Dirthara\Cache\Serialiser\NativeCacheSerialiser;
use Dirthara\Cache\Tests\Fixtures\PrivateCachedValue;
use Dirthara\Cache\Exception\CacheSerialisationException;
use Dirthara\Cache\Tests\Fixtures\SerialisationErrorValue;

use function serialize;
use function unserialize;
use function set_error_handler;
use function restore_error_handler;

#[CoversClass(NativeCacheSerialiser::class)]
#[UsesClass(CacheSerialisationException::class)]
#[UsesTrait(HasExceptionContext::class)]
final class NativeCacheSerialiserTest extends TestCase
{
    #[Test]
    public function it_serialises_with_native_serialisation(): void
    {
        self::assertSame(serialize(['id' => 42]), new NativeCacheSerialiser()->serialise(['id' => 42]));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function values(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'true' => [true];
        yield 'zero' => [0];
        yield 'a float' => [1.5];
        yield 'an empty string' => [''];
        yield 'a string' => ['ada@example.com'];
        yield 'an empty array' => [[]];
        yield 'a nested array' => [['user' => ['id' => 42, 'roles' => ['admin']]]];
    }

    #[Test]
    #[DataProvider('values')]
    public function it_restores_a_serialised_value(mixed $value): void
    {
        $serialiser = new NativeCacheSerialiser();

        self::assertSame($value, $serialiser->deserialise($serialiser->serialise($value)));
    }

    #[Test]
    public function it_restores_an_object_and_the_objects_it_holds(): void
    {
        $serialiser = new NativeCacheSerialiser();
        $value = new stdClass();
        $value->createdAt = new DateTimeImmutable('2026-10-05 12:00:00');

        // @mago-expect analysis:mixed-assignment The serialiser restores any value, which the assertion below narrows
        $restored = $serialiser->deserialise($serialiser->serialise($value));

        self::assertInstanceOf(stdClass::class, $restored);
        self::assertEquals($value, $restored);
        self::assertNotSame($value, $restored);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function unserialisableValues(): iterable
    {
        yield 'a closure' => [static function (): void {}, 'Closure'];
        yield 'an anonymous class' => [new class {}, 'class@anonymous'];
    }

    #[Test]
    #[DataProvider('unserialisableValues')]
    public function it_refuses_a_value_that_cannot_be_serialised(mixed $value, string $type): void
    {
        try {
            new NativeCacheSerialiser()->serialise($value);
            self::fail('A value that cannot be serialised was accepted.');
        } catch (CacheSerialisationException $exception) {
            self::assertNotNull($exception->getPrevious());
            self::assertSame(['type' => $type], $exception->context);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedPayloads(): iterable
    {
        yield 'garbage' => ['not a serialised value'];
        yield 'an empty payload' => [''];
        yield 'a truncated payload' => ['a:1:{s:5:"email";s:15:"ada@'];
        yield 'a valid payload followed by extra data' => [serialize(['id' => 42]) . 'x'];
    }

    #[Test]
    #[DataProvider('malformedPayloads')]
    public function it_refuses_a_malformed_payload(string $payload): void
    {
        $this->expectExceptionObject(CacheSerialisationException::unableToDeserialise());

        new NativeCacheSerialiser()->deserialise($payload);
    }

    #[Test]
    public function it_restores_the_previous_error_handler_after_a_malformed_payload(): void
    {
        $handler = static fn(): bool => true;
        set_error_handler($handler);

        try {
            new NativeCacheSerialiser()->deserialise('garbage');
            self::fail('A malformed payload was accepted.');
        } catch (CacheSerialisationException) {
            self::assertSame($handler, set_error_handler(null));
            restore_error_handler();
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function it_wraps_an_exception_thrown_while_restoring_an_object(): void
    {
        try {
            new NativeCacheSerialiser()->deserialise('O:11:"ArrayObject":4:{i:0;i:0;i:1;i:5;i:2;a:0:{}i:3;N;}');
            self::fail('An object that failed to restore was accepted.');
        } catch (CacheSerialisationException $exception) {
            self::assertNotNull($exception->getPrevious());
            self::assertSame(
                'Unable to deserialise a cached payload: the payload is malformed.',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function it_wraps_an_error_thrown_while_restoring_an_object(): void
    {
        try {
            new NativeCacheSerialiser()->deserialise('O:17:"DateTimeImmutable":1:{s:4:"date";i:1;}');
            self::fail('Invalid native object state was accepted.');
        } catch (CacheSerialisationException $exception) {
            self::assertInstanceOf(Error::class, $exception->getPrevious());
            self::assertSame([], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_payload_whose_class_does_not_exist(): void
    {
        $this->expectExceptionObject(CacheSerialisationException::unknownClass());

        new NativeCacheSerialiser()->deserialise('O:11:"App\\Removed":0:{}');
    }

    #[Test]
    public function it_keeps_the_payload_out_of_the_exception(): void
    {
        try {
            new NativeCacheSerialiser()->deserialise(serialize('secret-reference') . 'x');
            self::fail('A malformed payload was accepted.');
        } catch (CacheSerialisationException $exception) {
            self::assertStringNotContainsString('secret-reference', $exception->getMessage());
            self::assertSame([], $exception->context);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function missingClassPayloads(): iterable
    {
        $missing = 'O:11:"App\\Removed":0:{}';
        yield 'an array' => ['a:1:{i:0;' . $missing . '}'];
        yield 'nested arrays' => ['a:1:{i:0;a:1:{i:0;a:1:{i:0;' . $missing . '}}}'];
        yield 'an object' => ['O:8:"stdClass":1:{s:5:"child";' . $missing . '}'];
        yield 'a private property' => ['O:8:"stdClass":1:{s:14:"' . "\0Parent\0hidden" . '";' . $missing . '}'];
    }

    #[Test]
    #[DataProvider('missingClassPayloads')]
    public function it_refuses_missing_classes_anywhere_in_a_value(string $payload): void
    {
        $this->expectExceptionObject(CacheSerialisationException::unknownClass());

        new NativeCacheSerialiser()->deserialise($payload);
    }

    #[Test]
    public function it_traverses_cyclic_and_repeated_object_graphs(): void
    {
        $serialiser = new NativeCacheSerialiser();
        $parent = new stdClass();
        $child = new stdClass();
        $parent->child = $child;
        $parent->repeated = $child;
        $child->parent = $parent;
        // @mago-expect analysis:mixed-assignment The assertion narrows the restored value
        $restored = $serialiser->deserialise($serialiser->serialise($parent));

        self::assertInstanceOf(stdClass::class, $restored);
        self::assertSame($restored->child, $restored->repeated);
        self::assertInstanceOf(stdClass::class, $restored->child);
        self::assertSame($restored, $restored->child->parent);
    }

    #[Test]
    public function it_traverses_arrays_with_cyclic_and_repeated_references(): void
    {
        $serialiser = new NativeCacheSerialiser();
        $value = ['name' => 'Ada'];
        $value['self'] = &$value;
        $value['again'] = &$value;
        // @mago-expect analysis:mixed-assignment The assertion narrows the restored value
        $restored = $serialiser->deserialise($serialiser->serialise($value));

        self::assertIsArray($restored);
        self::assertIsArray($restored['self']);
        self::assertIsArray($restored['self']['again']);
        self::assertSame('Ada', $restored['self']['again']['name']);
    }

    #[Test]
    public function it_wraps_an_error_thrown_while_serialising(): void
    {
        $value = new SerialisationErrorValue();
        try {
            new NativeCacheSerialiser()->serialise($value);
            self::fail('A serialisation error escaped.');
        } catch (CacheSerialisationException $exception) {
            self::assertInstanceOf(Error::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_inspects_private_properties_in_a_real_object(): void
    {
        $value = new PrivateCachedValue(unserialize('O:11:"App\\Removed":0:{}'));
        $this->expectExceptionObject(CacheSerialisationException::unknownClass());

        new NativeCacheSerialiser()->deserialise(serialize($value));
    }

    #[Test]
    public function it_finds_a_missing_class_after_a_cyclic_reference(): void
    {
        $value = [];
        $value['self'] = &$value;
        $value['missing'] = unserialize('O:11:"App\\Removed":0:{}');
        $this->expectExceptionObject(CacheSerialisationException::unknownClass());

        new NativeCacheSerialiser()->deserialise(serialize($value));
    }
}
