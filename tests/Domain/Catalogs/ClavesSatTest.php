<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Catalogs;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Domain\Catalogs\Claves\ClavesSat;
use SplCfdi\Tests\Support\Fixtures;

/** Cada clave con nombre debe existir en el catálogo embebido que dice representar. */
final class ClavesSatTest extends TestCase
{
    public function testTodasLasClavesExistenEnSuCatalogoEmbebido(): void
    {
        $catalogs = Fixtures::catalogs();
        $faltantes = [];

        foreach (ClavesSat::porCatalogo() as $catalogo => $claves) {
            foreach ($claves as $constante => $clave) {
                if (!$catalogs->get($catalogo)->contains($clave)) {
                    $faltantes[] = "$constante ($clave) en $catalogo";
                }
            }
        }

        $this->assertSame([], $faltantes);
    }

    public function testSoloCitaCatalogosEmbebidos(): void
    {
        $this->assertSame([], array_diff(array_keys(ClavesSat::porCatalogo()), CatalogName::all()));
    }
}
