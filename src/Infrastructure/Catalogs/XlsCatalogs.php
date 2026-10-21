<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

use SplCfdi\Domain\Catalogs\Monedas;
use SplCfdi\Domain\Catalogs\RegimenesFiscales;
use SplCfdi\Domain\Catalogs\TasaOCuota;
use SplCfdi\Domain\Catalogs\TasasOCuotas;

/** Carga resources/catalogs/xls/*.php (generados por `bin/cfdi-catalogs xls`) validando su forma. */
final class XlsCatalogs
{
    public const DIR = 'xls';
    public const MONEDA = 'c_Moneda';
    public const REGIMEN_FISCAL = 'c_RegimenFiscal';
    public const TASA_O_CUOTA = 'c_TasaOCuota';

    public static function path(string $catalogsDir, string $name): string
    {
        return rtrim($catalogsDir, '/\\') . '/' . self::DIR . "/$name.php";
    }

    public static function monedas(string $catalogsDir): Monedas
    {
        $monedas = [];
        foreach (self::load($catalogsDir, self::MONEDA) as $codigo => $fila) {
            $f = self::fila($fila, self::MONEDA, (string) $codigo);
            $monedas[(string) $codigo] = [
                'descripcion' => self::texto($f, 'descripcion'),
                'decimales' => is_int($f['decimales'] ?? null) ? $f['decimales'] : self::invalido(self::MONEDA, (string) $codigo),
                'variacion' => self::textoONulo($f, 'variacion'),
            ];
        }

        return new Monedas($monedas);
    }

    public static function regimenesFiscales(string $catalogsDir): RegimenesFiscales
    {
        $regimenes = [];
        foreach (self::load($catalogsDir, self::REGIMEN_FISCAL) as $clave => $fila) {
            $f = self::fila($fila, self::REGIMEN_FISCAL, (string) $clave);
            $regimenes[(string) $clave] = [
                'descripcion' => self::texto($f, 'descripcion'),
                'fisica' => ($f['fisica'] ?? null) === true,
                'moral' => ($f['moral'] ?? null) === true,
            ];
        }

        return new RegimenesFiscales($regimenes);
    }

    public static function tasasOCuotas(string $catalogsDir): TasasOCuotas
    {
        $filas = [];
        foreach (self::load($catalogsDir, self::TASA_O_CUOTA) as $i => $fila) {
            $f = self::fila($fila, self::TASA_O_CUOTA, (string) $i);
            $filas[] = new TasaOCuota(
                impuesto: self::texto($f, 'impuesto'),
                tipoFactor: self::texto($f, 'factor'),
                minimo: self::textoONulo($f, 'minimo'),
                maximo: self::texto($f, 'maximo'),
                traslado: ($f['traslado'] ?? null) === true,
                retencion: ($f['retencion'] ?? null) === true,
                inicioVigencia: self::texto($f, 'inicio'),
                finVigencia: self::textoONulo($f, 'fin'),
            );
        }

        return new TasasOCuotas($filas);
    }

    /** @return array<mixed> */
    private static function load(string $catalogsDir, string $name): array
    {
        $file = self::path($catalogsDir, $name);
        if (!is_file($file)) {
            throw new \RuntimeException("Falta $file. Ejecuta: php bin/cfdi-catalogs xls --xls=catCFDI.xls");
        }

        return PhpDataFile::load($file);
    }

    /** @return array<mixed> */
    private static function fila(mixed $fila, string $name, string $clave): array
    {
        return is_array($fila) ? $fila : self::invalido($name, $clave);
    }

    /** @param array<mixed> $f */
    private static function texto(array $f, string $campo): string
    {
        return is_string($f[$campo] ?? null) ? $f[$campo] : throw new \RuntimeException("Campo $campo inválido en un catálogo xls.");
    }

    /** @param array<mixed> $f */
    private static function textoONulo(array $f, string $campo): ?string
    {
        return ($f[$campo] ?? null) === null ? null : self::texto($f, $campo);
    }

    private static function invalido(string $name, string $clave): never
    {
        throw new \RuntimeException("Fila \"$clave\" inválida en el catálogo $name (xls).");
    }
}
