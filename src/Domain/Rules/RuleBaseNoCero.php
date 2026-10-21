<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\Impuesto;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/** Un concepto con descuento total deja Base = 0, y el esquema exige Base > 0 en cada traslado/retención. */
final class RuleBaseNoCero implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $violaciones = [];

        foreach ($calculado->conceptos as $i => $cc) {
            // Con Cuota la base son unidades, distinta de la del concepto: se revisa la de cada impuesto.
            $grupos = ['Traslado' => $cc->traslados, 'Retencion' => $cc->retenciones];
            foreach ($grupos as $nodo => $impuestos) {
                foreach (array_values($impuestos) as $n => $impuesto) {
                    if ($this->esCero($impuesto->base)) {
                        $violaciones[] = new Violation('base-cero', sprintf(
                            '%s: la Base del %s de %s es 0; debe ser mayor que cero (revisa el descuento o las unidades de la cuota).',
                            Ubicacion::concepto($i, $cc->concepto), $nodo === 'Traslado' ? 'traslado' : 'retención',
                            Impuesto::nombre($impuesto->impuesto)
                        ), Ubicacion::campoConcepto($i, sprintf('%s[%d].Base', $nodo, $n + 1)));
                    }
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
