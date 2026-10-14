<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;
use SplCfdi\Domain\Contracts\Clock;

final class SystemClock implements Clock
{
    private readonly DateTimeZone $zone;

    /** @param DateTimeZone|null $zone zona del emisor; por defecto America/Mexico_City */
    public function __construct(?DateTimeZone $zone = null)
    {
        $this->zone = $zone ?? new DateTimeZone('America/Mexico_City');
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->zone);
    }
}
