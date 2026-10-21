<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/** Claves de c_Meses (InformacionGlobal): 01–12 son meses, 13–18 son bimestres. */
final class Meses
{
    public const CATALOGO = CatalogName::MESES;

    public const ENERO = '01';
    public const DICIEMBRE = '12';
    public const ENERO_FEBRERO = '13';
    public const NOVIEMBRE_DICIEMBRE = '18';

    /** @return array{string, string} primera y última clave válidas para la periodicidad */
    public static function rangoPara(string $periodicidad): array
    {
        return $periodicidad === Periodicidad::BIMESTRAL
            ? [self::ENERO_FEBRERO, self::NOVIEMBRE_DICIEMBRE]
            : [self::ENERO, self::DICIEMBRE];
    }
}
