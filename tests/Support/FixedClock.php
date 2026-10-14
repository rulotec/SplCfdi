<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Support;

use DateTimeImmutable;
use SplCfdi\Domain\Contracts\Clock;

final class FixedClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
