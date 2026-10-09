<?php
declare(strict_types=1);

namespace SplCfdi\Domain\Services;

use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Models\ImpuestoRetenido;
use SplCfdi\Domain\Models\ImpuestoRetenidoCalculado;
use SplCfdi\Domain\Models\ImpuestoRetenidoResumen;
use SplCfdi\Domain\Models\ImpuestoTrasladado;
use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;
use SplCfdi\Domain\Models\ImportesComprobante;
use SplCfdi\Domain\Models\ImpuestosComprobante;
use SplCfdi\Domain\Models\TipoFactor;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\ConceptoCalculado;

final class InvoiceCalculator
{
    public function __construct(
        private readonly DecimalMath $math,
        private readonly ImpuestoTrasladadoAggregator $aggregator,
        private readonly ImpuestoRetenidoAggregator $retencionAggregator,
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
        $todosTraslados = [];
        $todasRetenciones = [];

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
                $traslado = $this->calcularTraslado($base, $impuesto);
                $trasladosConcepto[] = $traslado;
                $todosTraslados[] = $traslado;
            }

            $retencionesConcepto = [];
            foreach ($concepto->impuestosRetenidos as $retencion) {
                $calculada = $this->calcularRetencion($base, $retencion);
                $retencionesConcepto[] = $calculada;
                $todasRetenciones[] = $calculada;
            }

            $conceptos[] = new ConceptoCalculado(
                $concepto, $importe, $descuento, $trasladosConcepto, $retencionesConcepto
            );
        }

        // Redondeo único sobre la suma agrupada (traslados)
        $resumenTraslados = array_map(
            fn (ImpuestoTrasladadoCalculado $t) => new ImpuestoTrasladadoCalculado(
                base: $this->math->round($t->base, $decimales),
                impuesto: $t->impuesto,
                tipoFactor: $t->tipoFactor,
                tasaOCuota: $t->tasaOCuota,
                importe: $t->importe === null ? null : $this->math->round($t->importe, $decimales),
            ),
            $this->aggregator->aggregate($todosTraslados)
        );

        // ... y sobre la suma agrupada por impuesto (retenciones)
        $resumenRetenciones = array_map(
            fn (ImpuestoRetenidoResumen $r) => new ImpuestoRetenidoResumen(
                $r->impuesto,
                $this->math->round($r->importe, $decimales)
            ),
            $this->retencionAggregator->aggregate($todasRetenciones)
        );

        // null cuando no hay nada que sumar (sin traslados, o solo Exento)
        $totalTraslados = $this->sumar(array_map(fn ($t) => $t->importe, $resumenTraslados), $decimales);
        $totalRetenciones = $this->sumar(array_map(fn ($r) => $r->importe, $resumenRetenciones), $decimales);

        $subTotalRedondeado = $this->math->round($subTotal, $decimales);
        $descuentoRedondeado = $hayDescuento ? $this->math->round($descuentoTotal, $decimales) : null;

        // Total = SubTotal − Descuento + Traslados − Retenciones
        $total = $this->math->round(
            $this->math->subtract(
                $this->math->add(
                    $this->math->subtract($subTotalRedondeado, $descuentoRedondeado ?? '0'),
                    $totalTraslados ?? '0'
                    ),
                $totalRetenciones ?? '0'
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
                traslados: $resumenTraslados,
                retenciones: $resumenRetenciones,
                totalImpuestosTrasladados: $totalTraslados,
                totalImpuestosRetenidos: $totalRetenciones,
            ),
        );
    }

    /**
     * Suma importes ya redondeados; ignora los null (Exento). Null si no hay nada que sumar.
     *
     * @param array<?string> $importes
     */
    private function sumar(array $importes, int $decimales): ?string
    {
        $importes = array_filter($importes, fn (?string $i) => $i !== null);

        if ($importes === []) {
            return null;
        }

        $suma = '0';
        foreach ($importes as $importe) {
            $suma = $this->math->add($suma, $importe);
        }

        return $this->math->round($suma, $decimales);
    }

    private function calcularTraslado(string $base, ImpuestoTrasladado $impuesto): ImpuestoTrasladadoCalculado
    {
        if ($impuesto->tipoFactor === TipoFactor::Exento) {
            return new ImpuestoTrasladadoCalculado($base, $impuesto->impuesto, TipoFactor::Exento, null, null);
        }

        $tasa = $this->math->format($impuesto->tasaOCuota, DecimalConfiguration::TASA_SCALE);

        return new ImpuestoTrasladadoCalculado(
            base: $base,
            impuesto: $impuesto->impuesto,
            tipoFactor: $impuesto->tipoFactor,
            tasaOCuota: $tasa,
            importe: $this->importeImpuesto($base, $tasa),
        );
    }

    private function calcularRetencion(string $base, ImpuestoRetenido $retencion): ImpuestoRetenidoCalculado
    {
        $tasa = $this->math->format($retencion->tasaOCuota, DecimalConfiguration::TASA_SCALE);

        return new ImpuestoRetenidoCalculado(
            base: $base,
            impuesto: $retencion->impuesto,
            tipoFactor: $retencion->tipoFactor,
            tasaOCuota: $tasa,
            importe: $this->importeImpuesto($base, $tasa),
        );
    }

    /** Base × tasa, a la escala de concepto (máx. 6 decimales). */
    private function importeImpuesto(string $base, string $tasa): string
    {
        return $this->math->round($this->math->multiply($base, $tasa), $this->decimalConfig->conceptScale);
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
}
