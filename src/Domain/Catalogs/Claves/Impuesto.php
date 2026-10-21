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

    /** "IVA (002)" para los mensajes; la clave sola si no tiene nombre aquí. */
    public static function nombre(string $clave): string
    {
        $nombre = match ($clave) {
            self::ISR => 'ISR',
            self::IVA => 'IVA',
            self::IEPS => 'IEPS',
            default => null,
        };

        return $nombre === null ? $clave : "$nombre ($clave)";
    }
}
