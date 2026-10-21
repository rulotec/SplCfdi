<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/** Claves de c_MetodoPago. */
final class MetodoPago
{
    public const CATALOGO = CatalogName::METODO_PAGO;

    /** Pago en una sola exhibición. */
    public const PUE = 'PUE';
    /** Pago en parcialidades o diferido. */
    public const PPD = 'PPD';
}
