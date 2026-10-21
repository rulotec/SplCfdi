<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/** Claves de c_TipoDeComprobante. */
final class TipoDeComprobante
{
    public const CATALOGO = CatalogName::TIPO_DE_COMPROBANTE;

    public const INGRESO = 'I';
    public const EGRESO = 'E';
    public const TRASLADO = 'T';
    public const NOMINA = 'N';
    public const PAGO = 'P';

    /** Tipos que no admiten Descuento ni el nodo Impuestos del comprobante. */
    public static function sinImpuestosNiDescuento(string $tipo): bool
    {
        return in_array($tipo, [self::TRASLADO, self::PAGO], true);
    }
}
