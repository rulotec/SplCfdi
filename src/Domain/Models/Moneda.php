<?php

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Configuration\DecimalConfiguration;

final class Moneda
{
    public function __construct(
        public readonly string $codigo,
        public readonly int $decimales,
    ) {
        if (!preg_match('/^[A-Z]{3}$/', $codigo)) {
            throw new \InvalidArgumentException("Código de moneda inválido: $codigo");
        }
        if ($decimales < 0 || $decimales > DecimalConfiguration::SAT_MAX_SCALE) {
            throw new \InvalidArgumentException(
                sprintf('Los decimales deben estar entre 0 y %d.', DecimalConfiguration::SAT_MAX_SCALE)
            );
        }
    }

    public static function mxn(): self { return new self('MXN', 2); }
}
