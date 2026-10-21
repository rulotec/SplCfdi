<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs;

/**
 * Catálogos pequeños que viajan embebidos. El valor es el nombre del tipo en catCFDI.xsd:
 * para agregar uno, añade su constante aquí y ejecuta `php bin/cfdi-catalogs write`.
 * Ver docs/catalogos.md.
 */
final class CatalogName
{
    public const FORMA_PAGO = 'c_FormaPago';
    public const METODO_PAGO = 'c_MetodoPago';
    public const TIPO_DE_COMPROBANTE = 'c_TipoDeComprobante';
    public const EXPORTACION = 'c_Exportacion';
    public const OBJETO_IMP = 'c_ObjetoImp';
    public const IMPUESTO = 'c_Impuesto';
    public const PERIODICIDAD = 'c_Periodicidad';
    public const MESES = 'c_Meses';
    public const REGIMEN_FISCAL = 'c_RegimenFiscal';
    public const USO_CFDI = 'c_UsoCFDI';

    /** @return string[] todas las constantes de esta clase: agregar una basta para embeberla */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
