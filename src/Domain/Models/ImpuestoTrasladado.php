<?php
declare(strict_types = 1);
namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Configuration\DecimalConfiguration;

final class ImpuestoTrasladado
{

    public function __construct(public readonly string $impuesto, public readonly string $tipoFactor, public readonly string $tasaOCuota)
    {
        if (!preg_match(sprintf('/^\d+(?:\.\d{1,%d})?$/', DecimalConfiguration::TASA_SCALE), $tasaOCuota)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'TasaOCuota inválida: "%s" (máximo %d decimales).',
                    $tasaOCuota, DecimalConfiguration::TASA_SCALE
                )
            );
        }
    }
}
