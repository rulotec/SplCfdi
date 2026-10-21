<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;

/** Con RFC genérico, el domicilio fiscal del receptor es el lugar de expedición. */
final class RuleDomicilioReceptorGenerico implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $comprobante = $calculado->comprobante;
        $receptor = $comprobante->receptor;

        if ($receptor->esRfcGenerico() && $receptor->domicilioFiscalReceptor !== $comprobante->lugarExpedicion) {
            return [new Violation('domicilio-receptor-generico', sprintf(
                'Con RFC genérico, DomicilioFiscalReceptor (%s) debe ser igual a LugarExpedicion (%s).',
                $receptor->domicilioFiscalReceptor, $comprobante->lugarExpedicion
            ), 'Receptor.DomicilioFiscalReceptor')];
        }

        return [];
    }
}
