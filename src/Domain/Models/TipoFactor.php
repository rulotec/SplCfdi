<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

enum TipoFactor: string
{
    case Tasa = 'Tasa';
    case Exento = 'Exento';
    // Cuota se omite a propósito: su Base es una cantidad, no un importe monetario.
}
