<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/** Claves de c_FormaPago que las reglas citan por nombre. */
final class FormaPago
{
    public const CATALOGO = CatalogName::FORMA_PAGO;

    /** Se usa cuando el pago no se recibió al emitir: va con MetodoPago PPD. */
    public const POR_DEFINIR = '99';
}
