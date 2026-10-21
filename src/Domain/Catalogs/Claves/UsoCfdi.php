<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/** Claves de c_UsoCFDI que las reglas citan por nombre; el resto vive en UsoCfdiMatrix. */
final class UsoCfdi
{
    public const CATALOGO = CatalogName::USO_CFDI;

    public const SIN_EFECTOS_FISCALES = 'S01';
    public const PAGOS = 'CP01';
}
