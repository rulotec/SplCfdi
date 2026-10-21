<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\MetodoPago;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/** Con InformacionGlobal, el MetodoPago debe ser PUE. */
final class RuleMetodoPagoPueEnGlobal implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $comprobante = $calculado->comprobante;

        if ($comprobante->informacionGlobal !== null && $comprobante->metodoPago !== MetodoPago::PUE) {
            return [new Violation('metodo-pago-global', sprintf(
                'En un CFDI global el MetodoPago debe ser %s (recibido: %s).',
                MetodoPago::PUE,
                $comprobante->metodoPago === null ? 'no informado' : '"' . $comprobante->metodoPago . '"'
            ), 'MetodoPago')];
        }

        return [];
    }
}
