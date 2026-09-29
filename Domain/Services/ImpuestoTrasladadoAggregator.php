<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Services;

use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;

final class ImpuestoTrasladadoAggregator
{
    public function __construct(
        private readonly DecimalCalculator $decimalCalculator
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
                $impuesto->tipoFactor,
                $impuesto->tasaOCuota
            ]);

            if (!isset($agrupados[$clave])) {
                $agrupados[$clave] = $impuesto;
                continue;
            }

            $existente = $agrupados[$clave];

            $base = $this->decimalCalculator->add($existente->base, $impuesto->base);
            $importe = $this->decimalCalculator->add($existente->importe, $impuesto->importe);

            $agrupados[$clave] = new ImpuestoTrasladadoCalculado(
                base: $base,
                impuesto: $existente->impuesto,
                tipoFactor: $existente->tipoFactor,
                tasaOCuota: $existente->tasaOCuota,
                importe: $importe
            );
        }

        return array_values($agrupados);
    }
}
