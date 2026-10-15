<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;
use SplCfdi\Domain\Models\TipoFactor;

/**
 * ObjetoImp coherente con el desglose del concepto:
 *  - 01 (no objeto): sin Impuestos;
 *  - 02 (sí objeto): con Impuestos;
 *  - un traslado Exento solo es válido con 02.
 */
final class RuleObjetoImpCoherente implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $violaciones = [];

        foreach ($calculado->conceptos as $i => $cc) {
            $objeto = $cc->concepto->objetoImp;
            $etiqueta = sprintf('Concepto %d ("%s")', $i + 1, $cc->concepto->descripcion);
            $tieneImpuestos = $cc->traslados !== [] || $cc->retenciones !== [];
            $tieneExento = array_filter(
                $cc->traslados,
                fn (ImpuestoTrasladadoCalculado $t) => $t->tipoFactor === TipoFactor::Exento
            ) !== [];

            if ($objeto === '01' && $tieneImpuestos) {
                $violaciones[] = new Violation('objeto-imp-01-con-impuestos', sprintf(
                    '%s: con ObjetoImp 01 no debe existir el nodo Impuestos.', $etiqueta
                ));
            }

            if ($objeto === '02' && !$tieneImpuestos) {
                $violaciones[] = new Violation('objeto-imp-02-sin-impuestos', sprintf(
                    '%s: con ObjetoImp 02 debe existir el desglose de Impuestos.', $etiqueta
                ));
            }

            if ($tieneExento && $objeto !== '02') {
                $violaciones[] = new Violation('objeto-imp-exento', sprintf(
                    '%s: un traslado Exento exige ObjetoImp 02 (recibido: "%s").', $etiqueta, $objeto
                ));
            }
        }

        return $violaciones;
    }
}
