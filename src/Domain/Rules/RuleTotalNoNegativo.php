<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;

/** Las retenciones no pueden dejar el Total en negativo. */
final class RuleTotalNoNegativo implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $total = $calculado->importes->total;

        // DecimalMath::round nunca devuelve "-0.00": un signo menos implica un valor realmente negativo.
        if (str_starts_with($total, '-')) {
            return [new Violation('total-negativo', sprintf(
                'El Total (%s) no puede ser negativo; revisa las retenciones.', $total
            ))];
        }

        return [];
    }
}
