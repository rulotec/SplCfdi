<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Catalogs;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Infrastructure\Catalogs\CatalogDriftChecker;
use SplCfdi\Infrastructure\Catalogs\CatalogExclusions;
use SplCfdi\Infrastructure\Catalogs\CatalogXsdReader;
use SplCfdi\Infrastructure\Catalogs\EmbeddedCatalogs;

/**
 * Falla cuando resources/catalogs se desfasa del catCFDI.xsd que viaja en resources/xsd.
 * Arreglo: php bin/cfdi-catalogs write  (y revisar los avisos de la matriz).
 */
final class ShippedCatalogsTest extends TestCase
{
    private const XSD = __DIR__ . '/../../../resources/xsd/catCFDI.xsd';

    public function testLosCatalogosEmbebidosCoincidenConElXsd(): void
    {
        if (!is_file(self::XSD)) {
            $this->markTestSkipped('No existe resources/xsd/catCFDI.xsd.');
        }

        $xsd = CatalogExclusions::load(EmbeddedCatalogs::defaultDir())
            ->filter((new CatalogXsdReader())->read(self::XSD, CatalogName::all()));
        $embedded = [];
        foreach (CatalogName::all() as $name) {
            $embedded[$name] = EmbeddedCatalogs::read(EmbeddedCatalogs::defaultDir(), $name);
        }

        $checker = new CatalogDriftChecker();
        $problemas = [];
        foreach ($checker->compare($embedded, $xsd) as $d) {
            $problemas[] = $d->missingInXsd
                ? "{$d->catalog}: no está en el XSD"
                : sprintf('%s: nuevos [%s], bajas [%s]', $d->catalog, implode(',', $d->added), implode(',', $d->removed));
        }
        array_push($problemas, ...$checker->matrixIssues($xsd));

        $this->assertSame([], $problemas, "Catálogos desfasados:\n- " . implode("\n- ", $problemas));
    }
}
