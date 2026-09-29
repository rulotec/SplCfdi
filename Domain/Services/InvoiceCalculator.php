<?php
declare(strict_types=1);

namespace SplCfdi\Domain\Services;

use InvalidArgumentException;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Models\ImpuestoTrasladado;
use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;
use SplCfdi\Domain\Models\ImportesComprobante;
use SplCfdi\Domain\Models\ImpuestosComprobante;
use SplCfdi\Domain\Models\ResultadoCalculoComprobante;

final class InvoiceCalculator
{
    public function __construct(
        private readonly DecimalCalculator $decimalCalculator,
        private readonly ImpuestoTrasladadoAggregator $aggregator
    ) {}

    public function calculate(Comprobante $comprobante): ResultadoCalculoComprobante
    {
        $subTotal = '0.000000';
        $totalImpuestosTrasladados = '0.000000';

        /** @var ImpuestoTrasladadoCalculado[] $traslados */
        $traslados = [];

        foreach ($comprobante->conceptos as $concepto) {
            $subTotal = $this->decimalCalculator->add(
                $subTotal,
                $concepto->importe
            );

            $impuestosCalculados = [];

            foreach ($concepto->impuestosTrasladados as $impuesto) {
                $impuestoCalculado = $this->calcularImpuesto($concepto, $impuesto);

                $impuestosCalculados[] = $impuestoCalculado;
                $traslados[] = $impuestoCalculado;

                $totalImpuestosTrasladados = $this->decimalCalculator->add(
                    $totalImpuestosTrasladados,
                    $impuestoCalculado->importe
                );
            }

            $concepto->setImpuestosTrasladadosCalculados($impuestosCalculados);
        }

        $trasladosAgrupados = $this->aggregator->aggregate($traslados);

        $subTotalFormateado = $this->decimalCalculator->format($subTotal);

        $totalFormateado = $this->decimalCalculator->format(
            $this->decimalCalculator->add(
                $subTotal,
                $totalImpuestosTrasladados
            )
        );

        $importes = new ImportesComprobante(
            subTotal: $subTotalFormateado,
            total: $totalFormateado
        );

        $impuestos = new ImpuestosComprobante(
            traslados: $trasladosAgrupados,
            totalImpuestosTrasladados:
            $this->decimalCalculator->format(
                $totalImpuestosTrasladados
            )
        );

        return new ResultadoCalculoComprobante(
            importes: $importes,
            impuestos: $impuestos
        );
    }

    private function calcularImpuesto(
        Concepto $concepto,
        ImpuestoTrasladado $impuesto
    ): ImpuestoTrasladadoCalculado {
        $base = $concepto->importe;

        $importe = $this->decimalCalculator->multiply(
            $base,
            $impuesto->tasaOCuota
        );

        return new ImpuestoTrasladadoCalculado(
            base: $base,
            impuesto: $impuesto->impuesto,
            tipoFactor: $impuesto->tipoFactor,
            tasaOCuota: $impuesto->tasaOCuota,
            importe: $importe
        );
    }

    private function determinarBase(Concepto $concepto): float
    {
        // Primera versión:
        // la base corresponde al importe del concepto.
        return $this->toFloat($concepto->importe);
    }

    private function toFloat(string $value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "El importe '{$value}' no es numérico."
            );
        }

        return (float) $value;
    }

    private function formatAmount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}