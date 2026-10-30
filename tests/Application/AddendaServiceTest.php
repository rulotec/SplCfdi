<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Application;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Addendas\DatosAddenda;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\EjemplosAddenda;

/** Agregar la addenda a un CFDI ya timbrado. */
final class AddendaServiceTest extends TestCase
{
    /** @param Violation[] $violaciones @return string[] */
    private function codigos(array $violaciones): array
    {
        return array_map(fn (Violation $v) => $v->code, $violaciones);
    }

    public function testAgregaLaAddendaAUnCfdiTimbradoSinTocarLoDemas(): void
    {
        $original = EjemplosAddenda::xml('Stellantis.xml');
        $timbrado = EjemplosAddenda::sinAddenda($original);

        $resultado = CfdiFactory::createAddendaService()->add($timbrado, EjemplosAddenda::stellantis());

        $this->assertSame([], $this->codigos($resultado->violations));
        $xml = (string) $resultado->xml;
        $this->assertSame(
            EjemplosAddenda::addendaCanonica($this->ppyEsperada()),
            EjemplosAddenda::addendaCanonica($xml),
        );
        // Lo que no es la addenda queda igual: sello y timbre incluidos.
        $this->assertSame($this->canonicoSinAddenda($timbrado), $this->canonicoSinAddenda($xml));
        $this->assertStringContainsString('TimbreFiscalDigital', $xml);
    }

    public function testReemplazaLaAddendaQueYaTenia(): void
    {
        $resultado = CfdiFactory::createAddendaService()->add(EjemplosAddenda::xml('Stellantis.xml'), EjemplosAddenda::stellantis());

        $this->assertSame(1, substr_count((string) $resultado->xml, '<cfdi:Addenda>'));
        $this->assertSame(EjemplosAddenda::addendaCanonica($this->ppyEsperada()), EjemplosAddenda::addendaCanonica((string) $resultado->xml));
    }

    public function testSinDatosDeAddendaNoHayNadaQueAgregar(): void
    {
        $c = EjemplosAddenda::stellantis();
        $sinAddenda = new Comprobante(
            $c->version, $c->fecha, $c->moneda, $c->tipoDeComprobante, $c->exportacion, $c->lugarExpedicion,
            $c->emisor, $c->receptor, $c->conceptos, folio: $c->folio,
        );

        $resultado = CfdiFactory::createAddendaService()->add(EjemplosAddenda::xml('Stellantis.xml'), $sinAddenda);

        $this->assertSame(['addenda-sin-datos'], $this->codigos($resultado->violations));
        $this->assertNull($resultado->xml);
    }

    public function testConDatosIncompletosNoDevuelveXml(): void
    {
        $c = EjemplosAddenda::stellantis();
        $incompleto = new Comprobante(
            $c->version, $c->fecha, $c->moneda, $c->tipoDeComprobante, $c->exportacion, $c->lugarExpedicion,
            $c->emisor, $c->receptor, $c->conceptos, folio: $c->folio, addenda: new DatosAddenda('PPY'),
        );

        $resultado = CfdiFactory::createAddendaService()->add(EjemplosAddenda::xml('Stellantis.xml'), $incompleto);

        $this->assertContains('Addenda.codigoProveedor', array_map(fn (Violation $v) => $v->campo, $resultado->violations));
        $this->assertNull($resultado->xml);
    }

    public function testRechazaUnXmlQueNoEsCfdi(): void
    {
        $service = CfdiFactory::createAddendaService();

        $this->assertSame(['xml-mal-formado'], $this->codigos($service->add('<cfdi:Comprobante', EjemplosAddenda::stellantis())->violations));
        $this->assertSame(['xml-no-cfdi'], $this->codigos($service->add('<foo/>', EjemplosAddenda::stellantis())->violations));
    }

    /** La addenda del ejemplo con los decimales del XSD (ver EjemplosAddenda::todos()). */
    private function ppyEsperada(): string
    {
        [, $diferencias] = EjemplosAddenda::todos()['Stellantis.xml'];

        return str_replace(array_keys($diferencias), array_values($diferencias), EjemplosAddenda::xml('Stellantis.xml'));
    }

    private function canonicoSinAddenda(string $xml): string
    {
        $dom = new DOMDocument();
        $dom->loadXML(EjemplosAddenda::sinAddenda($xml));

        return (string) $dom->C14N();
    }
}
