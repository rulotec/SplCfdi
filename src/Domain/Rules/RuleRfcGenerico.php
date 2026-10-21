<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\RegimenFiscal;
use SplCfdi\Domain\Catalogs\Claves\TipoDeComprobante;
use SplCfdi\Domain\Catalogs\Claves\UsoCfdi;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/**
 * Receptor con RFC genérico (XAXX010101000 o XEXX010101000), sea o no un CFDI global:
 *  - RegimenFiscalReceptor 616, Sin obligaciones fiscales (CFDI40159);
 *  - UsoCFDI S01, Sin efectos fiscales (Guía de llenado), o CP01 en un CFDI de pago.
 */
final class RuleRfcGenerico implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $c = $calculado->comprobante;
        $receptor = $c->receptor;
        if (!$receptor->esRfcGenerico()) {
            return [];
        }

        $violaciones = [];

        if ($receptor->regimenFiscalReceptor !== RegimenFiscal::SIN_OBLIGACIONES_FISCALES) {
            $violaciones[] = new Violation('rfc-generico-regimen', sprintf(
                'Con el RFC genérico %s el RegimenFiscalReceptor debe ser "%s" (Sin obligaciones fiscales); recibido: "%s".',
                $receptor->rfc, RegimenFiscal::SIN_OBLIGACIONES_FISCALES, $receptor->regimenFiscalReceptor
            ), 'Receptor.RegimenFiscalReceptor');
        }

        $usoEsperado = $c->tipoDeComprobante === TipoDeComprobante::PAGO ? UsoCfdi::PAGOS : UsoCfdi::SIN_EFECTOS_FISCALES;
        if ($receptor->usoCFDI !== $usoEsperado) {
            $violaciones[] = new Violation('rfc-generico-uso-cfdi', sprintf(
                'Con el RFC genérico %s el UsoCFDI debe ser "%s"; recibido: "%s".',
                $receptor->rfc, $usoEsperado, $receptor->usoCFDI
            ), 'Receptor.UsoCFDI');
        }

        return $violaciones;
    }
}
