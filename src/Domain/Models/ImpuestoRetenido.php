<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Exceptions\InvalidFieldException;
use SplCfdi\Domain\Support\DecimalFormat;

final class ImpuestoRetenido
{
    public function __construct(
        public readonly string $impuesto,
        public readonly TipoFactor $tipoFactor,
        public readonly string $tasaOCuota,
    ) {
        if ($tipoFactor === TipoFactor::Exento) {
            throw new InvalidFieldException('TipoFactor', $tipoFactor->value, 'Una retención no puede ser Exento.');
        }

        DecimalFormat::assertValid($tasaOCuota, 'TasaOCuota', DecimalConfiguration::TASA_SCALE);
    }
}
