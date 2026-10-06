<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures\Integration\Fixtures;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
