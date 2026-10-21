<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Xml;

use PHPUnit\Framework\TestCase;
use SplCfdi\Infrastructure\Resources;
use SplCfdi\Infrastructure\Xml\ValidationSchemas;

final class ValidationSchemasTest extends TestCase
{
    public function testLaCopiaDeValidacionEstaAlDia(): void
    {
        $this->assertSame(
            [],
            ValidationSchemas::staleFiles(Resources::path('xsd'), Resources::path(ValidationSchemas::DIR)),
            'Ejecuta: php bin/cfdi-catalogs write'
        );
    }

    public function testReemplazaLosCatalogosGrandesPorSuPatron(): void
    {
        $catalogo = ValidationSchemas::render(Resources::path('xsd'))['catCFDI.xsd'];

        $this->assertStringNotContainsString('<xs:enumeration value="20000"/>', $catalogo);
        foreach (ValidationSchemas::PATTERNS as $nombre => $patron) {
            $this->assertStringContainsString(sprintf('<xs:simpleType name="%s">', $nombre), $catalogo);
            $this->assertStringContainsString(sprintf('<xs:pattern value="%s"/>', $patron), $catalogo);
        }
        $this->assertLessThan(1_000_000, strlen($catalogo));
    }

    public function testCopiaElRestoSinCambios(): void
    {
        $archivos = ValidationSchemas::render(Resources::path('xsd'));

        $this->assertSame(file_get_contents(Resources::path('xsd/cfdv40.xsd')), $archivos['cfdv40.xsd']);
        $this->assertSame(file_get_contents(Resources::path('xsd/tdCFDI.xsd')), $archivos['tdCFDI.xsd']);
    }
}
