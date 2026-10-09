<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Support\DecimalFormat;

final class ImpuestoTrasladado
{
    public function __construct(
        public readonly string $impuesto,
        public readonly TipoFactor $tipoFactor,
        public readonly ?string $tasaOCuota = null,
    ) {
            if ($tipoFactor === TipoFactor::Exento) {
                if ($tasaOCuota !== null) {
                    throw new \InvalidArgumentException('Un traslado Exento no lleva TasaOCuota.');
                }
                return;
            }

            if ($tasaOCuota === null) {
                throw new \InvalidArgumentException('TasaOCuota es obligatoria cuando el TipoFactor es Tasa.');
            }

            DecimalFormat::assertValid($tasaOCuota, 'TasaOCuota', DecimalConfiguration::TASA_SCALE);
    }
}