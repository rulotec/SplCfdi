<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class Moneda
{
    public function __construct(
        public readonly string $codigo,
        public readonly int $decimales,
    ) {}

    public static function mxn(): self { return new self('MXN', 2); }
}
