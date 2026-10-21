<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\TipoDeComprobante;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\ConceptoCalculado;

/** En un CFDI de traslado (T) o de pago (P) no debe existir Descuento (ni en el comprobante ni en los conceptos). */
final class RuleDescuentoPorTipoDeComprobante implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $tipo = $calculado->comprobante->tipoDeComprobante;
        if (!TipoDeComprobante::sinImpuestosNiDescuento($tipo)) {
            return [];
        }

        $enConceptos = array_filter(
            $calculado->conceptos,
            fn (ConceptoCalculado $c) => $c->descuento !== null
        );

        if ($calculado->importes->descuento === null && $enConceptos === []) {
            return [];
        }

        return [new Violation(
            'descuento-en-traslado-o-pago',
            sprintf('Cuando el TipoDeComprobante es %s el campo Descuento no debe existir.', $tipo),
            'Descuento'
        )];
    }
}
