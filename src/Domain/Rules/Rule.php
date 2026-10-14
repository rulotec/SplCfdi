<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;

interface Rule
{
    /** @return Violation[] vacío si el comprobante cumple la regla */
    public function check(ComprobanteCalculado $calculado): array;
}
