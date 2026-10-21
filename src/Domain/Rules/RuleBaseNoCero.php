<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;

/** Un concepto con descuento total deja Base = 0, y el esquema exige Base > 0 en cada traslado/retención. */
final class RuleBaseNoCero implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $violaciones = [];

        foreach ($calculado->conceptos as $i => $cc) {
            $tieneImpuestos = $cc->traslados !== [] || $cc->retenciones !== [];

            if ($tieneImpuestos && $this->esCero($cc->base)) {
                $violaciones[] = new Violation('base-cero', sprintf(
                    '%s: la Base de los impuestos es 0 tras el descuento; debe ser mayor que cero.',
                    Ubicacion::concepto($i, $cc->concepto)
                ), Ubicacion::campoConcepto($i, 'Descuento'));
            }
        }

        return $violaciones;
    }

    private function esCero(string $valor): bool
    {
        return preg_match('/^-?0(?:\.0+)?$/D', $valor) === 1;
    }
}
