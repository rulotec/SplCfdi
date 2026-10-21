<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;

/** InformacionGlobal.Año debe ser el año de la Fecha del comprobante o el inmediato anterior. */
final class RuleInformacionGlobalAnio implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $info = $calculado->comprobante->informacionGlobal;
        if ($info === null) {
            return [];
        }

        $anioFecha = (int) $calculado->comprobante->fecha->format('Y');

        if ($info->anio !== $anioFecha && $info->anio !== $anioFecha - 1) {
            return [new Violation('informacion-global-anio', sprintf(
                'InformacionGlobal Año (%d) debe ser el año de la Fecha (%d) o el anterior (%d).',
                $info->anio, $anioFecha, $anioFecha - 1
            ), 'InformacionGlobal.Año')];
        }

        return [];
    }
}
