<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;

/** El Descuento de un concepto no puede ser mayor que su Importe. */
final class RuleDescuentoNoMayorQueImporte implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $violaciones = [];

        foreach ($calculado->conceptos as $i => $cc) {
            // La base es Importe − Descuento: negativa solo si el descuento excede el importe.
            if (str_starts_with($cc->base, '-')) {
                $violaciones[] = new Violation('descuento-mayor-que-importe', sprintf(
                    '%s: el Descuento (%s) no puede ser mayor que el Importe (%s).',
                    Ubicacion::concepto($i, $cc->concepto), $cc->descuento, $cc->importe
                ), Ubicacion::campoConcepto($i, 'Descuento'));
            }
        }

        return $violaciones;
    }
}
