<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Catalogs;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Catalogs\ArrayCatalog;
use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Infrastructure\Catalogs\EmbeddedCatalogs;

final class EmbeddedCatalogsTest extends TestCase
{
    public function testCargaTodosLosCatalogos(): void
    {
        $catalogs = EmbeddedCatalogs::load();

        foreach (CatalogName::all() as $name) {
            $this->assertNotSame([], $catalogs->get($name)->values(), $name);
        }
    }

    public function testFormaDePagoConservaLosCerosYNoInventaValores(): void
    {
        $forma = EmbeddedCatalogs::load()->get(CatalogName::FORMA_PAGO);

        $this->assertTrue($forma->contains('01'));
        $this->assertTrue($forma->contains('99'));
        $this->assertFalse($forma->contains('1'));
        $this->assertFalse($forma->contains('07'));
        $this->assertFalse($forma->contains(''));
    }

    public function testElCatalogoGuardaLosValoresComoCadenas(): void
    {
        $catalog = new ArrayCatalog('c_Prueba', ['12', '01']);

        $this->assertSame(['12', '01'], $catalog->values());
        $this->assertTrue($catalog->contains('12'));
    }

    public function testPideUnCatalogoDesconocido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmbeddedCatalogs::load()->get('c_NoExiste');
    }

    public function testFallaConUnMensajeUtilSiFaltaElArchivo(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bin/cfdi-catalogs write');

        EmbeddedCatalogs::read('/ruta/que/no/existe', CatalogName::FORMA_PAGO);
    }
}
