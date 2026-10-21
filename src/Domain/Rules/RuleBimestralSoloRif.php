<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\Periodicidad;
use SplCfdi\Domain\Catalogs\Claves\RegimenFiscal;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/** La periodicidad bimestral de InformacionGlobal solo aplica a emisores del RIF. */
final class RuleBimestralSoloRif implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $comprobante = $calculado->comprobante;
        $info = $comprobante->informacionGlobal;

        if ($info !== null
            && $info->periodicidad === Periodicidad::BIMESTRAL
            && $comprobante->emisor->regimenFiscal !== RegimenFiscal::INCORPORACION_FISCAL
        ) {
            return [new Violation('bimestral-solo-rif', sprintf(
                'La periodicidad bimestral (%s) solo es válida con RegimenFiscal %s del emisor (recibido: "%s").',
                Periodicidad::BIMESTRAL, RegimenFiscal::INCORPORACION_FISCAL, $comprobante->emisor->regimenFiscal
            ), 'InformacionGlobal.Periodicidad')];
        }

        return [];
    }
}
