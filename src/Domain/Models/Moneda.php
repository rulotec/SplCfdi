<?php

namespace SplCfdi\Domain\Models;

final class Moneda
{
    public function __construct(
        public readonly string $codigo,
        public readonly int $decimales,
    ) {
        if (!preg_match('/^[A-Z]{3}$/', $codigo)) {
            throw new \InvalidArgumentException("Código de moneda inválido: $codigo");
        }
        if ($decimales < 0 || $decimales > 6) {
            throw new \InvalidArgumentException('Los decimales deben estar entre 0 y 6.');
        }
    }

    public static function mxn(): self { return new self('MXN', 2); }
}
