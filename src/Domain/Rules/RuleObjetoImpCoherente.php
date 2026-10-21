<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\ObjetoImp;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;
use SplCfdi\Domain\Models\TipoFactor;

/**
 * ObjetoImp coherente con el desglose del concepto:
 *  - 01 (no objeto): sin Impuestos;
 *  - 02 (sí objeto): con Impuestos;
 *  - un traslado Exento solo es válido con 02.
 * Las claves 03–08 aún no se validan (ver docs/funcionalidades.md).
 */
final class RuleObjetoImpCoherente implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $violaciones = [];

        foreach ($calculado->conceptos as $i => $cc) {
            $objeto = $cc->concepto->objetoImp;
            $etiqueta = Ubicacion::concepto($i, $cc);
            $campo = Ubicacion::campoConcepto($i, 'ObjetoImp');
            $tieneImpuestos = $cc->traslados !== [] || $cc->retenciones !== [];
            $tieneExento = array_filter(
                $cc->traslados,
                fn (ImpuestoTrasladadoCalculado $t) => $t->tipoFactor === TipoFactor::Exento
            ) !== [];

            if ($objeto === ObjetoImp::NO_OBJETO && $tieneImpuestos) {
                $violaciones[] = new Violation('objeto-imp-01-con-impuestos', sprintf(
                    '%s: con ObjetoImp %s (no objeto de impuesto) no debe existir el nodo Impuestos.',
                    $etiqueta, ObjetoImp::NO_OBJETO
                ), $campo);
            }

            if ($objeto === ObjetoImp::SI_OBJETO && !$tieneImpuestos) {
                $violaciones[] = new Violation('objeto-imp-02-sin-impuestos', sprintf(
                    '%s: con ObjetoImp %s (sí objeto de impuesto) debe existir el desglose de Impuestos.',
                    $etiqueta, ObjetoImp::SI_OBJETO
                ), $campo);
            }

            if ($tieneExento && $objeto !== ObjetoImp::SI_OBJETO) {
                $violaciones[] = new Violation('objeto-imp-exento', sprintf(
                    '%s: un traslado Exento exige ObjetoImp %s (recibido: "%s").',
                    $etiqueta, ObjetoImp::SI_OBJETO, $objeto
                ), $campo);
            }
        }

        return $violaciones;
    }
}
