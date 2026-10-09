<?php

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Support\DecimalFormat;

final class Moneda
{
    public function __construct(
        public readonly string $codigo,
        public readonly int $decimales,
    ) {
        if (!preg_match('/^[A-Z]{3}$/', $codigo)) {
            throw new \InvalidArgumentException("Código de moneda inválido: $codigo");
        }

        DecimalFormat::assertScale($decimales, DecimalConfiguration::SAT_MAX_SCALE, 'Los decimales');
    }

    public static function mxn(): self { return new self('MXN', 2); }
}
