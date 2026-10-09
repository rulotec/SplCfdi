<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Services;

use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;
use SplCfdi\Domain\Contracts\DecimalMath;

final class ImpuestoTrasladadoAggregator
{
    public function __construct(
        private readonly DecimalMath $decimalCalculator
    ) {}

    /**
     * @param ImpuestoTrasladadoCalculado[] $impuestos
     *
     * @return ImpuestoTrasladadoCalculado[]
     */
    public function aggregate(array $impuestos): array
    {
        $agrupados = [];

        foreach ($impuestos as $impuesto) {
            $clave = implode('|', [
                $impuesto->impuesto,
                $impuesto->tipoFactor->value,
                $impuesto->tasaOCuota ?? '',
            ]);

            if (!isset($agrupados[$clave])) {
                $agrupados[$clave] = $impuesto;
                continue;
            }

            $existente = $agrupados[$clave];

            $agrupados[$clave] = new ImpuestoTrasladadoCalculado(
                base: $this->decimalCalculator->add($existente->base, $impuesto->base),
                impuesto: $existente->impuesto,
                tipoFactor: $existente->tipoFactor,
                tasaOCuota: $existente->tasaOCuota,
                importe: $existente->importe === null || $impuesto->importe === null
                ? null
                : $this->decimalCalculator->add($existente->importe, $impuesto->importe),
                );
        }

        return array_values($agrupados);
    }
}
