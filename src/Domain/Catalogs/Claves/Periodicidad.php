<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/** Claves de c_Periodicidad (InformacionGlobal). */
final class Periodicidad
{
    public const CATALOGO = CatalogName::PERIODICIDAD;

    public const DIARIO = '01';
    public const SEMANAL = '02';
    public const QUINCENAL = '03';
    public const MENSUAL = '04';
    /** Solo para emisores del RIF (RegimenFiscal::INCORPORACION_FISCAL). */
    public const BIMESTRAL = '05';
}
