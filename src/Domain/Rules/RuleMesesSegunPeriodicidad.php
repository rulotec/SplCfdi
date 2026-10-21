<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\Meses;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/** Meses de InformacionGlobal: 01–12 con cualquier periodicidad salvo la bimestral, que usa 13–18. */
final class RuleMesesSegunPeriodicidad implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $info = $calculado->comprobante->informacionGlobal;
        if ($info === null) {
            return [];
        }

        [$primero, $ultimo] = Meses::rangoPara($info->periodicidad);

        if ((int) $info->meses < (int) $primero || (int) $info->meses > (int) $ultimo) {
            return [new Violation('meses-periodicidad', sprintf(
                'Meses "%s" no corresponde a la periodicidad "%s" de InformacionGlobal: se esperaba de %s a %s.',
                $info->meses, $info->periodicidad, $primero, $ultimo
            ), 'InformacionGlobal.Meses')];
        }

        return [];
    }
}
