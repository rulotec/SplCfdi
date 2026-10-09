<?php

declare(strict_types = 1);

namespace SplCfdi\Domain\Configuration;

use SplCfdi\Domain\Support\DecimalFormat;

final class DecimalConfiguration
{
    /** Máximo de decimales que el SAT permite en importes de concepto y en monedas. */
    public const SAT_MAX_SCALE = 6;

    /** Formato de TasaOCuota: siempre 6 decimales (0.160000). */
    public const TASA_SCALE = 6;

    public function __construct(public readonly int $calculationScale, public readonly int $maximumScale, public readonly int $conceptScale)
    {
        DecimalFormat::assertScale($maximumScale, self::SAT_MAX_SCALE, 'La escala máxima');
        DecimalFormat::assertScale($conceptScale, $maximumScale, 'La escala de concepto');

        // Cantidad × ValorUnitario y Base × Tasa pueden llegar a 12 decimales exactos.
        $minimo = self::SAT_MAX_SCALE * 2;
        if ($calculationScale < $minimo) {
            throw new \InvalidArgumentException(sprintf('La escala de cálculo debe ser al menos %d.', $minimo));
        }
    }

    public static function sat(): self
    {
        return new self(
            calculationScale: self::SAT_MAX_SCALE + self::TASA_SCALE, // 12
            maximumScale: self::SAT_MAX_SCALE,
            conceptScale: self::SAT_MAX_SCALE
        );
    }
}