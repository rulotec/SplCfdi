<?php
declare(strict_types=1);

namespace SplCfdi\Domain\Services;

use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Models\ImpuestoTrasladado;
use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;
use SplCfdi\Domain\Models\ImportesComprobante;
use SplCfdi\Domain\Models\ImpuestosComprobante;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\ConceptoCalculado;

final class InvoiceCalculator
{
    public function __construct(
        private readonly DecimalMath $math,
        private readonly ImpuestoTrasladadoAggregator $aggregator,
        private readonly DecimalConfiguration $decimalConfig
    ) {}

    public function calculate(Comprobante $comprobante): ComprobanteCalculado
    {
        $subTotal = '0';
        $conceptos = [];

        /** @var ImpuestoTrasladadoCalculado[] $traslados */
        $traslados = [];

        foreach ($comprobante->conceptos as $concepto) {
            $importe = $this->calcularImporte($concepto);
            $subTotal = $this->math->add($subTotal, $importe);

            $impuestosConcepto = [];
            foreach ($concepto->impuestosTrasladados as $impuesto) {
                $impuestoCalculado = $this->calcularImpuesto($importe, $impuesto);
                $impuestosConcepto[] = $impuestoCalculado;
                $traslados[] = $impuestoCalculado;
            }

            $conceptos[] = new ConceptoCalculado($concepto, $importe, $impuestosConcepto);
        }

        $decimales = $comprobante->moneda->decimales;
        $subTotalRedondeado = $this->math->round($subTotal, $decimales);

        $resumen = array_map(
            fn (ImpuestoTrasladadoCalculado $t) => new ImpuestoTrasladadoCalculado(
                base: $this->math->round($t->base, $decimales),
                impuesto: $t->impuesto,
                tipoFactor: $t->tipoFactor,
                tasaOCuota: $t->tasaOCuota,
                importe: $this->math->round($t->importe, $decimales),
                ),
            $this->aggregator->aggregate($traslados)
        );

        $totalTraslados = '0';
        foreach ($resumen as $t) {
            $totalTraslados = $this->math->add($totalTraslados, $t->importe);
        }
        $totalTraslados = $this->math->round($totalTraslados, $decimales);

        $impuestos = new ImpuestosComprobante(
            traslados: $resumen,
            totalImpuestosTrasladados: $totalTraslados,
        );

        $total = $this->math->add(
            $subTotalRedondeado,
            $totalTraslados
        );

        $totalRedondeado = $this->math->round($total, $decimales);

        $importes = new ImportesComprobante(
            subTotal: $subTotalRedondeado,
            total: $totalRedondeado
        );

        return new ComprobanteCalculado(
            comprobante: $comprobante,
            conceptos: $conceptos,
            importes: $importes,
            impuestos: $impuestos,
        );
    }

    /** Cantidad × ValorUnitario, a la escala de concepto (máx. 6 decimales). */
    private function calcularImporte(Concepto $concepto): string
    {
        return $this->math->round(
            $this->math->multiply($concepto->cantidad, $concepto->valorUnitario),
            $this->decimalConfig->conceptScale
        );
    }

    private function calcularImpuesto(string $base, ImpuestoTrasladado $impuesto): ImpuestoTrasladadoCalculado
    {
        $tasa = $this->math->format($impuesto->tasaOCuota, DecimalConfiguration::TASA_SCALE);

        $importe = $this->math->round(
            $this->math->multiply($base, $tasa),
            $this->decimalConfig->conceptScale
        );

        return new ImpuestoTrasladadoCalculado(
            base: $base,
            impuesto: $impuesto->impuesto,
            tipoFactor: $impuesto->tipoFactor,
            tasaOCuota: $tasa,
            importe: $importe
        );
    }
}