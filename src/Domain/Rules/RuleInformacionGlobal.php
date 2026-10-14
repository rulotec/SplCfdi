<?php
// src/Domain/Rules/InformacionGlobalRule.php
declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\Receptor;

/** InformacionGlobal obligatoria para el público en general, y datos del receptor en un CFDI global. */
final class RuleInformacionGlobal implements Rule
{
    private const REGIMEN_SIN_OBLIGACIONES = '616';
    private const USO_SIN_EFECTOS_FISCALES = 'S01';

    public function check(ComprobanteCalculado $calculado): array
    {
        $comprobante = $calculado->comprobante;
        $receptor = $comprobante->receptor;

        if ($comprobante->informacionGlobal === null) {
            return $receptor->esPublicoEnGeneral()
                ? [new Violation(
                    'informacion-global-requerida',
                    'El nodo InformacionGlobal es obligatorio cuando el receptor es XAXX010101000 / "PUBLICO EN GENERAL".'
                )]
                : [];
        }

        $esperados = [
            'Rfc' => [$receptor->rfc, Receptor::RFC_PUBLICO_GENERAL],
            'Nombre' => [$receptor->nombre, Receptor::NOMBRE_PUBLICO_GENERAL],
            'RegimenFiscalReceptor' => [$receptor->regimenFiscalReceptor, self::REGIMEN_SIN_OBLIGACIONES],
            'UsoCFDI' => [$receptor->usoCFDI, self::USO_SIN_EFECTOS_FISCALES],
        ];

        $violaciones = [];
        foreach ($esperados as $campo => [$actual, $esperado]) {
            if ($actual !== $esperado) {
                $violaciones[] = new Violation('informacion-global-receptor', sprintf(
                    'En un CFDI global el %s del Receptor debe ser "%s" (recibido: "%s").',
                    $campo, $esperado, $actual
                ));
            }
        }

        return $violaciones;
    }
}