<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/** Claves de c_Moneda que las reglas citan por nombre. */
final class Moneda
{
    public const CATALOGO = CatalogName::MONEDA;

    public const PESO_MEXICANO = 'MXN';
    /** Transacciones en que no interviene ninguna moneda (p. ej. CFDI de traslado o de pago). */
    public const SIN_MONEDA = 'XXX';
}
