<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\RegimenFiscal;
use SplCfdi\Domain\Catalogs\Claves\UsoCfdi;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\Receptor;

/** InformacionGlobal obligatoria para el público en general, y datos del receptor en un CFDI global. */
final class RuleInformacionGlobal implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $comprobante = $calculado->comprobante;
        $receptor = $comprobante->receptor;

        if ($comprobante->informacionGlobal === null) {
            return $receptor->esPublicoEnGeneral()
                ? [new Violation(
                    'informacion-global-requerida',
                    sprintf(
                        'El nodo InformacionGlobal es obligatorio cuando el receptor es %s / "%s".',
                        Receptor::RFC_PUBLICO_GENERAL, Receptor::NOMBRE_PUBLICO_GENERAL
                    ),
                    'InformacionGlobal'
                )]
                : [];
        }

        $esperados = [
            'Rfc' => [$receptor->rfc, Receptor::RFC_PUBLICO_GENERAL],
            'Nombre' => [$receptor->nombre, Receptor::NOMBRE_PUBLICO_GENERAL],
            'RegimenFiscalReceptor' => [$receptor->regimenFiscalReceptor, RegimenFiscal::SIN_OBLIGACIONES_FISCALES],
            'UsoCFDI' => [$receptor->usoCFDI, UsoCfdi::SIN_EFECTOS_FISCALES],
        ];

        $violaciones = [];
        foreach ($esperados as $campo => [$actual, $esperado]) {
            if ($actual !== $esperado) {
                $violaciones[] = new Violation('informacion-global-receptor', sprintf(
                    'En un CFDI global el %s del Receptor debe ser "%s" (recibido: "%s").',
                    $campo, $esperado, $actual
                ), "Receptor.$campo");
            }
        }

        return $violaciones;
    }
}
