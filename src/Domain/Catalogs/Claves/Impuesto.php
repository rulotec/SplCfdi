<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/** Claves de c_Impuesto. */
final class Impuesto
{
    public const CATALOGO = CatalogName::IMPUESTO;

    public const ISR = '001';
    public const IVA = '002';
    public const IEPS = '003';
}
