<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs\Claves;

/**
 * Registro de las clases de claves con nombre. `bin/cfdi-catalogs check` verifica que cada
 * constante siga existiendo en su catálogo: si el SAT da de baja una clave, el aviso sale ahí.
 */
final class ClavesSat
{
    /** @var list<class-string> */
    private const CLASES = [
        TipoDeComprobante::class,
        MetodoPago::class,
        ObjetoImp::class,
        Periodicidad::class,
        Meses::class,
        RegimenFiscal::class,
        UsoCfdi::class,
        Impuesto::class,
    ];

    /** @return array<string, array<string, string>> catálogo => [Clase::CONSTANTE => clave] */
    public static function porCatalogo(): array
    {
        $resultado = [];

        foreach (self::CLASES as $clase) {
            $constantes = (new \ReflectionClass($clase))->getConstants();
            $catalogo = $constantes['CATALOGO'];
            unset($constantes['CATALOGO']);

            $corto = substr($clase, strrpos($clase, '\\') + 1);
            foreach ($constantes as $nombre => $clave) {
                $resultado[$catalogo]["$corto::$nombre"] = $clave;
            }
        }

        return $resultado;
    }
}
