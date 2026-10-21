<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

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
            $campo = Ubicacion::campoConcepto($i, sprintf('Traslado[%d].TasaOCuota', $t + 1));

            if ($traslado->tipoFactor === TipoFactor::Exento) {
                if ($traslado->tasaOCuota !== null) {
                    $violaciones[] = new Violation('exento-con-tasa', sprintf(
                        '%s: un traslado Exento no lleva TasaOCuota (recibido: "%s").', $etiqueta, $traslado->tasaOCuota
                    ), $campo);
                }
            } elseif ($traslado->tasaOCuota === null) {
                $violaciones[] = new Violation('tasa-requerida', sprintf(
                    '%s: el traslado del impuesto %s necesita TasaOCuota (p. ej. "0.160000").', $etiqueta, $traslado->impuesto
                ), $campo);
            } else {
                $violaciones[] = Formato::decimal($traslado->tasaOCuota, "$etiqueta: TasaOCuota", $campo, $tasaMax);
            }
        }

        foreach (array_values($concepto->impuestosRetenidos) as $r => $retencion) {
            if ($retencion->tipoFactor === TipoFactor::Exento) {
                $violaciones[] = new Violation('retencion-exenta', sprintf(
                    '%s: una retención no puede tener TipoFactor Exento.', $etiqueta
                ), Ubicacion::campoConcepto($i, sprintf('Retencion[%d].TipoFactor', $r + 1)));
            }

            $violaciones[] = Formato::decimal(
                $retencion->tasaOCuota,
                "$etiqueta: TasaOCuota",
                Ubicacion::campoConcepto($i, sprintf('Retencion[%d].TasaOCuota', $r + 1)),
                $tasaMax
            );
        }

        return array_values(array_filter($violaciones));
    }
}
