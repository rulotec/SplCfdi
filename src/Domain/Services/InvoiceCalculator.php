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

        // array_values: lista, no mapa (array_merge(...) más abajo no admite claves de texto)
        $conceptos = array_map(
            fn (Concepto $c) => $this->calcularConcepto($c),
            array_values($comprobante->conceptos)
            );

        $traslados = $this->resumirTraslados($conceptos, $decimales);
        $retenciones = $this->resumirRetenciones($conceptos, $decimales);

        $subTotal = $this->sumar(array_map(fn (ConceptoCalculado $c) => $c->importe, $conceptos), $decimales)
        ?? $this->math->round('0', $decimales);
        $descuento = $this->sumar(array_map(fn (ConceptoCalculado $c) => $c->descuento, $conceptos), $decimales);
        $totalTraslados = $this->sumar(array_map(fn ($t) => $t->importe, $traslados), $decimales);
        $totalRetenciones = $this->sumar(array_map(fn ($r) => $r->importe, $retenciones), $decimales);

        // Total = SubTotal − Descuento + Traslados − Retenciones
        $total = $this->math->round(
            $this->math->subtract(
                $this->math->add(
                    $this->math->subtract($subTotal, $descuento ?? '0'),
                    $totalTraslados ?? '0'
                    ),
                $totalRetenciones ?? '0'
                ),
            $decimales
            );

        return new ComprobanteCalculado(
            comprobante: $comprobante,
            conceptos: $conceptos,
            importes: new ImportesComprobante(subTotal: $subTotal, total: $total, descuento: $descuento),
            impuestos: new ImpuestosComprobante(
                traslados: $traslados,
                retenciones: $retenciones,
                totalImpuestosTrasladados: $totalTraslados,
                totalImpuestosRetenidos: $totalRetenciones,
                ),
            );
    }

    private function calcularConcepto(Concepto $concepto): ConceptoCalculado
    {
        $importe = $this->calcularImporte($concepto);
        $descuento = $this->calcularDescuento($concepto, $importe);

        $base = $descuento === null
        ? $importe
        : $this->math->round($this->math->subtract($importe, $descuento), $this->decimalConfig->conceptScale);

        return new ConceptoCalculado(
            $concepto,
            $importe,
            $descuento,
            array_map(fn (ImpuestoTrasladado $i) => $this->calcularTraslado($base, $i), $concepto->impuestosTrasladados),
            array_map(fn (ImpuestoRetenido $r) => $this->calcularRetencion($base, $r), $concepto->impuestosRetenidos),
            );
    }

    /**
     * Redondeo único sobre la suma agrupada.
     *
     * @param ConceptoCalculado[] $conceptos
     * @return ImpuestoTrasladadoCalculado[]
     */
    private function resumirTraslados(array $conceptos, int $decimales): array
    {
        $todos = array_merge(...array_map(fn (ConceptoCalculado $c) => $c->traslados, $conceptos));

        return array_map(
            fn (ImpuestoTrasladadoCalculado $t) => new ImpuestoTrasladadoCalculado(
                base: $this->math->round($t->base, $decimales),
                impuesto: $t->impuesto,
                tipoFactor: $t->tipoFactor,
                tasaOCuota: $t->tasaOCuota,
                importe: $t->importe === null ? null : $this->math->round($t->importe, $decimales),
                ),
            $this->aggregator->aggregate($todos)
            );
    }

    /**
     * @param ConceptoCalculado[] $conceptos
     * @return ImpuestoRetenidoResumen[]
     */
    private function resumirRetenciones(array $conceptos, int $decimales): array
    {
        $todas = array_merge(...array_map(fn (ConceptoCalculado $c) => $c->retenciones, $conceptos));

        return array_map(
            fn (ImpuestoRetenidoResumen $r) => new ImpuestoRetenidoResumen(
                $r->impuesto,
                $this->math->round($r->importe, $decimales)
                ),
            $this->retencionAggregator->aggregate($todas)
            );
    }

    private function calcularTraslado(string $base, ImpuestoTrasladado $impuesto): ImpuestoTrasladadoCalculado
    {
        if ($impuesto->tipoFactor === TipoFactor::Exento) {
            return new ImpuestoTrasladadoCalculado($base, $impuesto->impuesto, TipoFactor::Exento, null, null);
        }

        $tasa = $this->normalizarTasa($impuesto->tasaOCuota);

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
        $tasa = $this->normalizarTasa($retencion->tasaOCuota);

        return new ImpuestoRetenidoCalculado(
            base: $base,
            impuesto: $retencion->impuesto,
            tipoFactor: $retencion->tipoFactor,
            tasaOCuota: $tasa,
            importe: $this->importeImpuesto($base, $tasa),
            );
    }

    /** Las tasas siempre se normalizan a TASA_SCALE decimales (0.16 → 0.160000). */
    private function normalizarTasa(string $tasa): string
    {
        return $this->math->format($tasa, DecimalConfiguration::TASA_SCALE);
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
