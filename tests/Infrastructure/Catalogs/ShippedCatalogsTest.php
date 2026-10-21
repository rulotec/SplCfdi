<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Catalogs;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Infrastructure\Catalogs\CatalogDriftChecker;
use SplCfdi\Infrastructure\Catalogs\CatalogExclusions;
use SplCfdi\Infrastructure\Catalogs\CatalogXsdReader;
use SplCfdi\Infrastructure\Catalogs\EmbeddedCatalogs;
use SplCfdi\Infrastructure\Resources;

/**
 * Falla cuando resources/catalogs se desfasa del catCFDI.xsd que viaja en resources/xsd.
 * Arreglo: php bin/cfdi-catalogs write  (y revisar los avisos de la matriz).
 */
final class ShippedCatalogsTest extends TestCase
{
    public function testLosCatalogosEmbebidosCoincidenConElXsd(): void
    {
        $xsdPath = Resources::path('xsd/catCFDI.xsd');
        if (!is_file($xsdPath)) {
            $this->markTestSkipped('No existe resources/xsd/catCFDI.xsd.');
        }

        $xsd = CatalogExclusions::load(EmbeddedCatalogs::defaultDir())
            ->filter((new CatalogXsdReader())->read($xsdPath, CatalogName::all()));
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
        array_push($problemas, ...$checker->matrixIssues($xsd), ...$checker->clavesIssues($xsd));

        $this->assertSame([], $problemas, "Catálogos desfasados:\n- " . implode("\n- ", $problemas));
    }
}
