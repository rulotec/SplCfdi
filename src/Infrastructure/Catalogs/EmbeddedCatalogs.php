<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

use SplCfdi\Domain\Catalogs\ArrayCatalog;
use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Domain\Catalogs\Catalogs;

/** Carga los catálogos embebidos: un archivo resources/catalogs/<c_Nombre>.php por catálogo. */
final class EmbeddedCatalogs
{
    public static function defaultDir(): string
    {
        return __DIR__ . '/../../../resources/catalogs';
    }

    public static function path(string $dir, string $name): string
    {
        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name . '.php';
    }

    public static function load(?string $dir = null): Catalogs
    {
        $dir ??= self::defaultDir();

        return new Catalogs(array_map(
            fn (string $name) => new ArrayCatalog($name, self::read($dir, $name)),
            CatalogName::all()
        ));
    }

    /** @return string[] */
    public static function read(string $dir, string $name): array
    {
        $file = self::path($dir, $name);

        if (!is_file($file)) {
            throw new \RuntimeException(
                "Falta el catálogo embebido $name ($file). Ejecuta: php bin/cfdi-catalogs write"
            );
        }

        $values = require $file;

        if (!is_array($values)) {
            throw new \RuntimeException("El archivo $file debe devolver un arreglo.");
        }

        return array_map('strval', array_values($values));
    }
}
