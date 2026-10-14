<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;

/** En un CFDI de traslado (T) o de pago (P) el nodo Impuestos del comprobante no debe existir. */
final class RuleSinImpuestosEnTrasladoOPago implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $impuestos = $calculado->impuestos;
        $tipo = $calculado->comprobante->tipoDeComprobante;

        if (in_array($tipo, ['T', 'P'], true) && ($impuestos->traslados !== [] || $impuestos->retenciones !== [])) {
            return [new Violation(
                'impuestos-en-traslado-o-pago',
                sprintf('Cuando el TipoDeComprobante es %s el nodo Impuestos no debe existir.', $tipo)
            )];
        }

        return [];
    }
}