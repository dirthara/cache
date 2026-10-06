<?php

declare(strict_types=1);

namespace Dirthara\Cache\Tests\Fixtures;

final readonly class PrivateCachedValue
{
    public function __construct(
        // @mago-expect analysis:unused-property Native serialisation reads this private property
        private mixed $value,
    ) {}
}
