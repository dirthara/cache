<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Cache\ValueObject\StoredValue;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StoredValue::class)]
final class StoredValueTest extends TestCase
{
    #[Test]
    public function it_carries_a_payload_and_when_it_expires(): void
    {
        $expiresAt = new DateTimeImmutable('2026-10-05 12:00:00');

        $value = new StoredValue('payload', $expiresAt);

        self::assertSame('payload', $value->payload);
        self::assertSame($expiresAt, $value->expiresAt);
    }

    #[Test]
    public function it_can_carry_a_payload_that_never_expires(): void
    {
        self::assertNull(new StoredValue('payload', null)->expiresAt);
    }
}
