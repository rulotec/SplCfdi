<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\Impuesto;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Models\TipoFactor;

/** Forma de cada concepto y de sus impuestos: lo que el cálculo necesita para poder hacerse. */
final class RuleFormatoConceptos implements FormatRule
{
    public function check(Comprobante $comprobante): array
    {
        $violaciones = [];

        foreach (array_values($comprobante->conceptos) as $i => $concepto) {
            array_push($violaciones, ...$this->revisarConcepto($i, $concepto));
        }

        return $violaciones;
    }

    /** @return Violation[] */
    private function revisarConcepto(int $i, Concepto $concepto): array
    {
        $etiqueta = Ubicacion::concepto($i, $concepto);
        $max = DecimalConfiguration::SAT_MAX_SCALE;
        $tasaMax = DecimalConfiguration::TASA_SCALE;

        $violaciones = [
            Formato::claveProdServ($concepto->claveProdServ, "$etiqueta: ClaveProdServ", Ubicacion::campoConcepto($i, 'ClaveProdServ')),
            Formato::decimal($concepto->cantidad, "$etiqueta: Cantidad", Ubicacion::campoConcepto($i, 'Cantidad'), $max),
            Formato::decimal($concepto->valorUnitario, "$etiqueta: ValorUnitario", Ubicacion::campoConcepto($i, 'ValorUnitario'), $max),
        ];

        if ($concepto->descuento !== null) {
            $violaciones[] = Formato::decimal($concepto->descuento, "$etiqueta: Descuento", Ubicacion::campoConcepto($i, 'Descuento'), $max);
        }

        foreach (array_values($concepto->impuestosTrasladados) as $t => $traslado) {
            $nodo = Ubicacion::campoConcepto($i, sprintf('Traslado[%d]', $t + 1));

            if ($traslado->tipoFactor === TipoFactor::Exento) {
                if ($traslado->tasaOCuota !== null) {
                    $violaciones[] = new Violation('exento-con-tasa', sprintf(
                        '%s: un traslado Exento no lleva TasaOCuota (recibido: "%s").', $etiqueta, $traslado->tasaOCuota
                    ), "$nodo.TasaOCuota");
                }
            } elseif ($traslado->tasaOCuota === null) {
                $violaciones[] = new Violation('tasa-requerida', sprintf(
                    '%s: el traslado de %s necesita TasaOCuota (p. ej. "0.160000").', $etiqueta, Impuesto::nombre($traslado->impuesto)
                ), "$nodo.TasaOCuota");
            } else {
                $violaciones[] = Formato::decimal($traslado->tasaOCuota, "$etiqueta: TasaOCuota", "$nodo.TasaOCuota", $tasaMax);
            }

            array_push($violaciones, ...$this->revisarBaseCuota($etiqueta, $nodo, $traslado->tipoFactor, $traslado->base));
        }

        foreach (array_values($concepto->impuestosRetenidos) as $r => $retencion) {
            $nodo = Ubicacion::campoConcepto($i, sprintf('Retencion[%d]', $r + 1));

            if ($retencion->tipoFactor === TipoFactor::Exento) {
                $violaciones[] = new Violation('retencion-exenta', sprintf(
                    '%s: una retención no puede tener TipoFactor Exento.', $etiqueta
                ), "$nodo.TipoFactor");
            }

            $violaciones[] = Formato::decimal($retencion->tasaOCuota, "$etiqueta: TasaOCuota", "$nodo.TasaOCuota", $tasaMax);
            array_push($violaciones, ...$this->revisarBaseCuota($etiqueta, $nodo, $retencion->tipoFactor, $retencion->base));
        }

        return array_values(array_filter($violaciones));
    }

    /**
     * Con Cuota la Base son unidades (litros, piezas…) y el host debe indicarlas; con Tasa o Exento
     * la Base es el importe del concepto y no se captura.
     *
     * @return Violation[]
     */
    private function revisarBaseCuota(string $etiqueta, string $nodo, TipoFactor $factor, ?string $base): array
    {
        if ($factor !== TipoFactor::Cuota) {
            return $base === null ? [] : [new Violation('base-solo-con-cuota', sprintf(
                '%s: la Base solo se indica con TipoFactor Cuota; con %s se calcula del importe del concepto.',
                $etiqueta, $factor->value
            ), "$nodo.Base")];
        }

        if ($base === null) {
            return [new Violation('base-cuota-requerida', sprintf(
                '%s: con TipoFactor Cuota hay que indicar la Base, es decir, las unidades gravadas (p. ej. litros).', $etiqueta
            ), "$nodo.Base")];
        }

        $violacion = Formato::decimal($base, "$etiqueta: Base", "$nodo.Base", DecimalConfiguration::SAT_MAX_SCALE);

        return $violacion === null ? [] : [$violacion];
    }
}
