<?php
declare(strict_types=1);

namespace SplCfdi\Domain\Services;

use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Models\ImpuestoTrasladado;
use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;
use SplCfdi\Domain\Models\ImportesComprobante;
use SplCfdi\Domain\Models\ImpuestosComprobante;
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\ConceptoCalculado;

final class InvoiceCalculator
{
    public function __construct(
        private readonly DecimalMath $math,
        private readonly ImpuestoTrasladadoAggregator $aggregator
    ) {}

    public function calculate(Comprobante $comprobante): ComprobanteCalculado
    {
        $subTotal = '0.000000';
        $totalImpuestosTrasladados = '0.000000';
        $conceptos = [];

        /** @var ImpuestoTrasladadoCalculado[] $traslados */
        $traslados = [];

        foreach ($comprobante->conceptos as $concepto) {
            $subTotal = $this->math->add($subTotal, $concepto->importe);

            $impuestosCalculados = [];

            foreach ($concepto->impuestosTrasladados as $impuesto) {
                $impuestoCalculado = $this->calcularImpuesto($concepto, $impuesto);

                $impuestosCalculados[] = $impuestoCalculado;
                $traslados[] = $impuestoCalculado;

                $totalImpuestosTrasladados = $this->math->add(
                    $totalImpuestosTrasladados,
                    $impuestoCalculado->importe
                );
            }

            $conceptos[] = new ConceptoCalculado($concepto, $traslados);
        }



        $decimales = $comprobante->moneda->decimales;
        $subTotalRedondeado = $this->math->round($subTotal, $decimales);
        $totalImpuestosTrasladadosRedondeados = $this->math->round($totalImpuestosTrasladados, $decimales);

        $total = $this->math->add(
            $subTotalRedondeado,
            $totalImpuestosTrasladadosRedondeados
        );

        $totalRedondeado = $this->math->round($total, $decimales);

        $importes = new ImportesComprobante(
            subTotal: $subTotalRedondeado,
            total: $totalRedondeado
        );

        $impuestos = new ImpuestosComprobante(
            traslados: $this->aggregator->aggregate($traslados),
            totalImpuestosTrasladados: $totalImpuestosTrasladadosRedondeados
        );

        return new ComprobanteCalculado(
            comprobante: $comprobante,
            conceptos: $conceptos,
            importes: $importes,
            impuestos: $impuestos,
        );
    }

    private function calcularImpuesto(
        Concepto $concepto,
        ImpuestoTrasladado $impuesto
    ): ImpuestoTrasladadoCalculado {
        $base = $concepto->importe;

        $importe = $this->math->multiply(
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
}