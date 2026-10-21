<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Exceptions\InvalidFieldException;
use SplCfdi\Domain\Support\DecimalFormat;

final class Moneda
{
    public function __construct(
        public readonly string $codigo,
        public readonly int $decimales,
    ) {
        if (!preg_match('/^[A-Z]{3}$/D', $codigo)) {
            throw new InvalidFieldException('Moneda', $codigo, sprintf(
                'Moneda inválida: "%s" (código ISO 4217 de tres letras mayúsculas, p. ej. "MXN").', $codigo
            ));
        }

        DecimalFormat::assertScale($decimales, DecimalConfiguration::SAT_MAX_SCALE, 'Los decimales');
    }

    public static function mxn(): self { return new self('MXN', 2); }
}
