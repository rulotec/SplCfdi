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
            foreach ([...$cc->traslados, ...$cc->retenciones] as $impuesto) {
                if ($this->esCero($impuesto->base)) {
                    $violaciones[] = new Violation('base-cero', sprintf(
                        'Concepto %d ("%s"): la Base del impuesto %s es 0 tras el descuento; debe ser mayor que cero.',
                        $i + 1, $cc->concepto->descripcion, $impuesto->impuesto
                    ));
                    break; // una violación por concepto
                }
            }
        }

        return $violaciones;
    }

    private function esCero(string $valor): bool
    {
        return preg_match('/^-?0(?:\.0+)?$/D', $valor) === 1;
    }
}
