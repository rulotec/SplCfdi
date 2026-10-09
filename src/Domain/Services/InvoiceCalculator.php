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
        $decimales = $comprobante->moneda->decimales;
        $escala = $this->decimalConfig->conceptScale;

        $subTotal = '0';
        $descuentoTotal = '0';
        $hayDescuento = false;
        $conceptos = [];
        $todos = [];

        foreach ($comprobante->conceptos as $concepto) {
            $importe = $this->calcularImporte($concepto);
            $descuento = $this->calcularDescuento($concepto, $importe);

            $base = $descuento === null
            ? $importe
            : $this->math->round($this->math->subtract($importe, $descuento), $escala);

            $subTotal = $this->math->add($subTotal, $importe);
            if ($descuento !== null) {
                $descuentoTotal = $this->math->add($descuentoTotal, $descuento);
                $hayDescuento = true;
            }

            $trasladosConcepto = [];
            foreach ($concepto->impuestosTrasladados as $impuesto) {
                $traslado = $this->calcularImpuesto($base, $impuesto);
                $trasladosConcepto[] = $traslado;
                $todos[] = $traslado;
            }

            $conceptos[] = new ConceptoCalculado($concepto, $importe, $descuento, $trasladosConcepto);
        }

        $resumen = array_map(
            fn (ImpuestoTrasladadoCalculado $t) => new ImpuestoTrasladadoCalculado(
                base: $this->math->round($t->base, $decimales),
                impuesto: $t->impuesto,
                tipoFactor: $t->tipoFactor,
                tasaOCuota: $t->tasaOCuota,
                importe: $this->math->round($t->importe, $decimales),
            ),
            $this->aggregator->aggregate($todos)
        );

        $totalTraslados = '0';
        foreach ($resumen as $t) {
            $totalTraslados = $this->math->add($totalTraslados, $t->importe);
        }
        $totalTraslados = $this->math->round($totalTraslados, $decimales);

        $subTotalRedondeado = $this->math->round($subTotal, $decimales);
        $descuentoRedondeado = $hayDescuento ? $this->math->round($descuentoTotal, $decimales) : null;

        // Total = SubTotal − Descuento + Traslados (retenciones se restarán después)
        $total = $this->math->round(
            $this->math->add(
                $this->math->subtract($subTotalRedondeado, $descuentoRedondeado ?? '0'),
                $totalTraslados
            ),
            $decimales
        );

        return new ComprobanteCalculado(
            comprobante: $comprobante,
            conceptos: $conceptos,
            importes: new ImportesComprobante(
                subTotal: $subTotalRedondeado,
                total: $total,
                descuento: $descuentoRedondeado,
            ),
            impuestos: new ImpuestosComprobante(
                traslados: $resumen,
                totalImpuestosTrasladados: $totalTraslados,
            ),
        );
    }

    /** Descuento del concepto a la escala de concepto; null si no hay. */
    private function calcularDescuento(Concepto $concepto, string $importe): ?string
    {
        if ($concepto->descuento === null) {
            return null;
        }

        $descuento = $this->math->round($concepto->descuento, $this->decimalConfig->conceptScale);

        if ($this->math->compare($descuento, $importe) > 0) {
            throw new \InvalidArgumentException(sprintf(
                'El Descuento (%s) no puede ser mayor que el Importe (%s) del concepto "%s".',
                $descuento, $importe, $concepto->descripcion
                ));
        }

        return $descuento;
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