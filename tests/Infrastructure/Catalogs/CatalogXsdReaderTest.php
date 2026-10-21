<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Catalogs;

use PHPUnit\Framework\TestCase;
use SplCfdi\Infrastructure\Catalogs\CatalogFileWriter;
use SplCfdi\Infrastructure\Catalogs\CatalogXsdReader;

final class CatalogXsdReaderTest extends TestCase
{
    private const XSD = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:catCFDI="http://www.sat.gob.mx/sitio_internet/cfd/catalogos" xmlns:xs="http://www.w3.org/2001/XMLSchema" targetNamespace="http://www.sat.gob.mx/sitio_internet/cfd/catalogos" elementFormDefault="unqualified" attributeFormDefault="unqualified">
	<xs:simpleType name="c_MetodoPago">
		<xs:restriction base="xs:string">
			<xs:whiteSpace value="collapse"/>
			<xs:enumeration value="PUE"/>
			<xs:enumeration value="PPD"/>
		</xs:restriction>
	</xs:simpleType>
	<xs:simpleType name="c_Meses">
		<xs:restriction base="xs:string">
			<xs:enumeration value="01"/>
			<xs:enumeration value="12"/>
		</xs:restriction>
	</xs:simpleType>
	<xs:simpleType name="c_Patron">
		<xs:restriction base="xs:string">
			<xs:pattern value="[A-Z]{3}"/>
		</xs:restriction>
	</xs:simpleType>
</xs:schema>
XML;

    private string $file;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'xsd');
        file_put_contents($this->file, self::XSD);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testLeeLosValoresEnumeradosEnOrdenYConservaLosCeros(): void
    {
        $r = (new CatalogXsdReader())->read($this->file, ['c_MetodoPago', 'c_Meses']);

        $this->assertSame(['PUE', 'PPD'], $r['c_MetodoPago']);
        $this->assertSame(['01', '12'], $r['c_Meses']);
    }

    public function testOmiteTiposInexistentesOSinEnumeracion(): void
    {
        $r = (new CatalogXsdReader())->read($this->file, ['c_Patron', 'c_Inexistente']);

        $this->assertSame([], $r);
    }

    public function testFallaSiNoExisteElArchivo(): void
    {
        $this->expectException(\RuntimeException::class);
        (new CatalogXsdReader())->read('/ruta/que/no/existe.xsd', ['c_Meses']);
    }

    public function testFallaSiElArchivoNoEsXml(): void
    {
        file_put_contents($this->file, 'esto no es xml');

        $this->expectException(\RuntimeException::class);
        (new CatalogXsdReader())->read($this->file, ['c_Meses']);
    }

    public function testElArchivoGeneradoSePuedeCargarDeVuelta(): void
    {
        file_put_contents($this->file, CatalogFileWriter::render('c_Meses', ['01', '12', '18']));

        $this->assertSame(['01', '12', '18'], require $this->file);
    }
}
