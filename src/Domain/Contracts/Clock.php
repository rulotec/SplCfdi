<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Contracts;

interface Clock
{
    /** La hora actual en la zona horaria del emisor (la del lugar de expedición). */
    public function now(): \DateTimeImmutable;
}
