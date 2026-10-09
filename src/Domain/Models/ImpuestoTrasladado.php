<?php
declare(strict_types = 1);
namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Support\DecimalFormat;

final class ImpuestoTrasladado
{

    public function __construct(public readonly string $impuesto, public readonly string $tipoFactor, public readonly string $tasaOCuota)
    {
        DecimalFormat::assertValid($tasaOCuota, 'TasaOCuota', DecimalConfiguration::TASA_SCALE);
    }
}
