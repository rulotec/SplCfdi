<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/** Claves de c_ObjetoImp: si el concepto es objeto de impuesto y si lleva desglose. */
final class ObjetoImp
{
    public const CATALOGO = CatalogName::OBJETO_IMP;

    public const NO_OBJETO = '01';
    public const SI_OBJETO = '02';
    public const SI_OBJETO_SIN_DESGLOSE = '03';
    public const SI_OBJETO_NO_CAUSA = '04';
    public const SI_OBJETO_IVA_CREDITO_PODEBI = '05';
    public const SI_OBJETO_IVA_SIN_TRASLADO = '06';
    public const SIN_TRASLADO_IVA_CON_DESGLOSE_IEPS = '07';
    public const SIN_TRASLADO_IVA_SIN_DESGLOSE_IEPS = '08';
}
