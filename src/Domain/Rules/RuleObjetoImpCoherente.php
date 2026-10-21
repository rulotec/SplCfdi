<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\Impuesto;
use SplCfdi\Domain\Catalogs\Claves\ObjetoImp;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\ConceptoCalculado;
use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;
use SplCfdi\Domain\Models\TipoFactor;

/**
 * ObjetoImp coherente con el desglose de impuestos del concepto (matriz de errores del SAT):
 *  - 02: debe existir el nodo Impuestos (CFDI40171);
 *  - 01, 03, 04, 05: no debe existir (CFDI40172);
 *  - 06, 08: sin IVA ni IEPS; puede haber retención de ISR (CFDI140226);
 *  - 07: sin IVA y con traslado de IEPS; puede haber retención de ISR o IEPS (CFDI140227);
 *  - un traslado Exento solo es válido con 02 (solo se reporta si no hay otra violación).
 */
final class RuleObjetoImpCoherente implements Rule
{
    private const SIN_NODO_IMPUESTOS = [
        ObjetoImp::NO_OBJETO,
        ObjetoImp::SI_OBJETO_SIN_DESGLOSE,
        ObjetoImp::SI_OBJETO_NO_CAUSA,
        ObjetoImp::SI_OBJETO_IVA_CREDITO_PODEBI,
    ];

    private const SIN_IVA_NI_IEPS = [
        ObjetoImp::SI_OBJETO_IVA_SIN_TRASLADO,
        ObjetoImp::SIN_TRASLADO_IVA_SIN_DESGLOSE_IEPS,
    ];

    public function check(ComprobanteCalculado $calculado): array
    {
        $violaciones = [];

        foreach ($calculado->conceptos as $i => $cc) {
            array_push($violaciones, ...$this->revisar($cc, Ubicacion::concepto($i, $cc->concepto), Ubicacion::campoConcepto($i, 'ObjetoImp')));
        }

        return $violaciones;
    }

    /** @return Violation[] */
    private function revisar(ConceptoCalculado $cc, string $etiqueta, string $campo): array
    {
        $objeto = $cc->concepto->objetoImp;
        $impuestos = $this->impuestosDelConcepto($cc);
        $violaciones = [];

        if ($objeto === ObjetoImp::SI_OBJETO && $impuestos === []) {
            $violaciones[] = new Violation('objeto-imp-02-sin-impuestos', sprintf(
                '%s: con ObjetoImp %s (sí objeto de impuesto) debe existir el desglose de Impuestos.', $etiqueta, $objeto
            ), $campo);
        }

        if (in_array($objeto, self::SIN_NODO_IMPUESTOS, true) && $impuestos !== []) {
            $violaciones[] = new Violation('objeto-imp-sin-impuestos', sprintf(
                '%s: con ObjetoImp %s no debe existir el nodo Impuestos del concepto.', $etiqueta, $objeto
            ), $campo);
        }

        if (in_array($objeto, self::SIN_IVA_NI_IEPS, true)) {
            $prohibidos = array_intersect($impuestos, [Impuesto::IVA, Impuesto::IEPS]);
            if ($prohibidos !== []) {
                $violaciones[] = new Violation('objeto-imp-sin-iva-ieps', sprintf(
                    '%s: con ObjetoImp %s no debe haber traslados ni retenciones de IVA o IEPS; solo puede haber retención de ISR.',
                    $etiqueta, $objeto
                ), $campo);
            }
        }

        if ($objeto === ObjetoImp::SIN_TRASLADO_IVA_CON_DESGLOSE_IEPS) {
            if (in_array(Impuesto::IVA, $impuestos, true)) {
                $violaciones[] = new Violation('objeto-imp-sin-iva', sprintf(
                    '%s: con ObjetoImp %s no debe haber traslados ni retenciones de IVA.', $etiqueta, $objeto
                ), $campo);
            }

            $trasladaIeps = array_filter($cc->traslados, fn (ImpuestoTrasladadoCalculado $t) => $t->impuesto === Impuesto::IEPS) !== [];
            if (!$trasladaIeps) {
                $violaciones[] = new Violation('objeto-imp-07-sin-ieps', sprintf(
                    '%s: con ObjetoImp %s debe existir el traslado de IEPS (003).', $etiqueta, $objeto
                ), $campo);
            }
        }

        $tieneExento = array_filter(
            $cc->traslados,
            fn (ImpuestoTrasladadoCalculado $t) => $t->tipoFactor === TipoFactor::Exento
        ) !== [];

        // Si ya hay una violación, ese mensaje explica mejor el problema.
        if ($tieneExento && $objeto !== ObjetoImp::SI_OBJETO && $violaciones === []) {
            $violaciones[] = new Violation('objeto-imp-exento', sprintf(
                '%s: un traslado Exento exige ObjetoImp %s (recibido: "%s").', $etiqueta, ObjetoImp::SI_OBJETO, $objeto
            ), $campo);
        }

        return $violaciones;
    }

    /** @return string[] claves de impuesto de traslados y retenciones del concepto */
    private function impuestosDelConcepto(ConceptoCalculado $cc): array
    {
        return array_map(fn ($impuesto) => $impuesto->impuesto, [...$cc->traslados, ...$cc->retenciones]);
    }
}
