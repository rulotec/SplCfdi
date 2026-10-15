<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;

/** La periodicidad bimestral (05) de InformacionGlobal solo aplica a emisores del RIF (621). */
final class RuleBimestralSoloRif implements Rule
{
    private const BIMESTRAL = '05';
    private const REGIMEN_RIF = '621';

    public function check(ComprobanteCalculado $calculado): array
    {
        $comprobante = $calculado->comprobante;
        $info = $comprobante->informacionGlobal;

        if ($info !== null
            && $info->periodicidad === self::BIMESTRAL
            && $comprobante->emisor->regimenFiscal !== self::REGIMEN_RIF
        ) {
            return [new Violation('bimestral-solo-rif', sprintf(
                'La periodicidad bimestral (05) solo es válida con RegimenFiscal %s del emisor (recibido: "%s").',
                self::REGIMEN_RIF, $comprobante->emisor->regimenFiscal
            ))];
        }

        return [];
    }
}
