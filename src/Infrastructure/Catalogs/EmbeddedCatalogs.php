<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

use SplCfdi\Domain\Catalogs\ArrayCatalog;
use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Domain\Catalogs\Catalogs;
use SplCfdi\Infrastructure\Resources;

/** Carga los catálogos embebidos: un archivo resources/catalogs/<c_Nombre>.php por catálogo. */
final class EmbeddedCatalogs
{
    public static function defaultDir(): string
    {
        return Resources::path('catalogs');
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

    /** @return list<string> */
    public static function read(string $dir, string $name): array
    {
        $file = self::path($dir, $name);

        if (!is_file($file)) {
            throw new \RuntimeException(
                "Falta el catálogo embebido $name ($file). Ejecuta: php bin/cfdi-catalogs write"
            );
        }

        return PhpDataFile::strings(PhpDataFile::load($file), $file);
    }
}
