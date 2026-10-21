<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\Comprobante;

/**
 * Revisa la forma de los datos capturados, antes de calcular: si alguna falla, el cálculo no es posible
 * (p. ej. Cantidad "abc") y solo se reportan estas violaciones.
 */
interface FormatRule
{
    /** @return Violation[] vacío si los datos tienen la forma esperada */
    public function check(Comprobante $comprobante): array;
}
