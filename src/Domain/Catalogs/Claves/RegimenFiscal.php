<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

use SplCfdi\Domain\Catalogs\CatalogName;

/**
 * Claves de c_RegimenFiscal que las reglas citan por nombre.
 * La relación completa régimen × UsoCFDI vive en UsoCfdiMatrix.
 */
final class RegimenFiscal
{
    public const CATALOGO = CatalogName::REGIMEN_FISCAL;

    public const GENERAL_PERSONAS_MORALES = '601';
    public const SIN_OBLIGACIONES_FISCALES = '616';
    public const INCORPORACION_FISCAL = '621';
}
