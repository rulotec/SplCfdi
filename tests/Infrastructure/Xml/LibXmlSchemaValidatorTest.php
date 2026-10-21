<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Xml;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Infrastructure\Cfdi\SimpleCfdiXmlGenerator;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;
use SplCfdi\Tests\Support\Fixtures;

final class LibXmlSchemaValidatorTest extends TestCase
{
    private LibXmlSchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new LibXmlSchemaValidator();
    }

    public function testUnXmlGeneradoYSinSelloCumpleConElEsquema(): void
    {
        $this->assertSame([], $this->validator->validate($this->xml()));
    }

    public function testRespetaElSelloSiYaViene(): void
    {
        $violaciones = $this->validator->validate($this->modificar('/cfdi:Comprobante', 'NoCertificado', '123'));

        $this->assertSame(['xsd-longitud'], $this->codigos($violaciones));
        $this->assertSame('NoCertificado', $violaciones[0]->campo);
    }

    public function testRechazaXmlVacio(): void
    {
        $this->assertSame(['xml-vacio'], $this->codigos($this->validator->validate('  ')));
    }

    public function testRechazaXmlMalFormado(): void
    {
        $violaciones = $this->validator->validate('<cfdi:Comprobante>');

        $this->assertSame(['xml-mal-formado'], $this->codigos($violaciones));
        $this->assertStringContainsString('línea 1', $violaciones[0]->message);
    }

    public function testRechazaUnaRaizQueNoEsComprobante(): void
    {
        $violaciones = $this->validator->validate('<foo/>');

        $this->assertSame(['xsd-estructura'], $this->codigos($violaciones));
        $this->assertStringContainsString('cfdi:Comprobante', $violaciones[0]->message);
    }

    public function testFallaSiNoExisteElXsd(): void
    {
        $this->expectException(\RuntimeException::class);
        (new LibXmlSchemaValidator('/ruta/que/no/existe.xsd'))->validate('<foo/>');
    }

    /** @return iterable<string, array{string, string, string, string, string, string}> */
    public static function errores(): iterable
    {
        yield 'catálogo' => ['/cfdi:Comprobante/cfdi:Receptor', 'UsoCFDI', 'ZZZ', 'xsd-catalogo', 'Receptor.UsoCFDI',
            'Receptor: UsoCFDI "ZZZ" no existe en el catálogo c_UsoCFDI.'];
        yield 'patrón con explicación' => ['/cfdi:Comprobante/cfdi:Emisor', 'Rfc', 'NO-ES-UN-RFC1', 'xsd-patron', 'Emisor.Rfc',
            'Emisor: Rfc "NO-ES-UN-RFC1" no tiene el formato requerido: se espera un RFC de 12 (persona moral) o 13 (persona física) caracteres.'];
        yield 'patrón de catálogo grande' => ['/cfdi:Comprobante', 'LugarExpedicion', '1234', 'xsd-patron', 'LugarExpedicion',
            'Comprobante: LugarExpedicion "1234" no tiene el formato requerido: se espera un código postal de cinco dígitos.'];
        yield 'longitud con explicación' => ['/cfdi:Comprobante/cfdi:Emisor', 'Rfc', 'AAA0101', 'xsd-longitud', 'Emisor.Rfc',
            'Emisor: Rfc tiene 7 caracteres; el mínimo es 12: se espera un RFC de 12 (persona moral) o 13 (persona física) caracteres.'];
        yield 'mínimo' => ['//cfdi:Concepto', 'Cantidad', '0', 'xsd-minimo', 'Concepto[1].Cantidad',
            'Concepto 1 ("Prueba"): Cantidad "0" debe ser al menos 0.000001.'];
        yield 'longitud' => ['//cfdi:Concepto', 'Unidad', str_repeat('x', 21), 'xsd-longitud', 'Concepto[1].Unidad',
            'Concepto 1 ("Prueba"): Unidad tiene 21 caracteres; el máximo es 20.'];
        yield 'decimales' => ['//cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado', 'Base', '1.1234567', 'xsd-decimales',
            'Concepto[1].Traslado[1].Base', 'Concepto 1 ("Prueba"), Traslado 1: Base "1.1234567" tiene más de 6 decimales.'];
        yield 'tipo' => ['/cfdi:Comprobante', 'Fecha', 'ayer', 'xsd-tipo', 'Fecha',
            'Comprobante: Fecha "ayer" no es una fecha y hora válida (AAAA-MM-DDThh:mm:ss).'];
        yield 'patrón sin explicación' => ['/cfdi:Comprobante/cfdi:Receptor', 'Nombre', 'RECEPTOR|S.A.', 'xsd-patron', 'Receptor.Nombre',
            'Receptor: Nombre "RECEPTOR|S.A." no tiene el formato que exige el SAT (revisa caracteres no permitidos o espacios).'];
        yield 'resumen de impuestos' => ['/cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado', 'TipoFactor', 'Otro',
            'xsd-catalogo', 'Impuestos.Traslado[1].TipoFactor', 'Impuestos, Traslado 1: TipoFactor "Otro" no existe en el catálogo c_TipoFactor.'];
        yield 'no permitido' => ['/cfdi:Comprobante/cfdi:Receptor', 'Inventado', 'x', 'xsd-no-permitido', 'Receptor.Inventado',
            'Receptor: el atributo Inventado no está permitido.'];
    }

    #[DataProvider('errores')]
    public function testTraduceLosErroresDelEsquema(
        string $xpath, string $atributo, string $valor, string $codigo, string $campo, string $mensaje,
    ): void {
        $violaciones = $this->validator->validate($this->modificar($xpath, $atributo, $valor));

        $this->assertCount(1, $violaciones, implode("\n", array_map(fn (Violation $v) => $v->message, $violaciones)));
        $this->assertSame([$codigo, $campo, $mensaje], [$violaciones[0]->code, $violaciones[0]->campo, $violaciones[0]->message]);
    }

    public function testReportaUnAtributoObligatorioFaltante(): void
    {
        $violaciones = $this->validator->validate($this->modificar('/cfdi:Comprobante/cfdi:Receptor', 'UsoCFDI', null));

        $this->assertSame(['xsd-requerido'], $this->codigos($violaciones));
        $this->assertSame('Receptor.UsoCFDI', $violaciones[0]->campo);
        $this->assertSame('Receptor: falta el atributo obligatorio UsoCFDI.', $violaciones[0]->message);
    }

    public function testReportaUnNodoFueraDeLugar(): void
    {
        $dom = $this->dom();
        $xp = $this->xp($dom);
        $dom->documentElement?->appendChild($this->elemento($xp, '/cfdi:Comprobante/cfdi:Emisor'));   // al final

        $violaciones = $this->validator->validate((string) $dom->saveXML());

        // libxml se detiene en el primer nodo que ya no cuadra: el Receptor, que quedó donde iba el Emisor
        $this->assertSame(['xsd-estructura'], $this->codigos($violaciones));
        $this->assertSame('Receptor', $violaciones[0]->campo);
        $this->assertSame('Receptor: el nodo no va en esta posición; se esperaba CfdiRelacionados o Emisor.', $violaciones[0]->message);
    }

    public function testNumeraLosConceptosYSusImpuestos(): void
    {
        $xml = $this->xml([Fixtures::concepto('10.00'), Fixtures::concepto('20.00')]);
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $this->elemento($this->xp($dom), '(//cfdi:Concepto)[2]')->setAttribute('ClaveUnidad', 'ZZ9');

        $violaciones = $this->validator->validate((string) $dom->saveXML());

        // ZZ9 no existe, pero tiene la forma de una clave: la copia de validación no trae la enumeración (lo revisa RuleCatalogos)
        $this->assertSame([], $violaciones);

        $this->elemento($this->xp($dom), '(//cfdi:Concepto)[2]')->setAttribute('ClaveUnidad', 'E');
        $violaciones = $this->validator->validate((string) $dom->saveXML());
        $this->assertSame(['Concepto[2].ClaveUnidad'], array_map(fn (Violation $v) => $v->campo, $violaciones));
    }

    /** @param \SplCfdi\Domain\Models\Concepto[]|null $conceptos */
    private function xml(?array $conceptos = null): string
    {
        $calculado = CfdiFactory::createCalculator()->calculate(
            Fixtures::comprobante($conceptos ?? [Fixtures::concepto('1000.00')])
        );

        return (new SimpleCfdiXmlGenerator())->generate($calculado);
    }

    private function dom(): DOMDocument
    {
        $dom = new DOMDocument();
        $dom->loadXML($this->xml());

        return $dom;
    }

    private function xp(DOMDocument $dom): DOMXPath
    {
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');

        return $xp;
    }

    private function elemento(DOMXPath $xp, string $xpath): DOMElement
    {
        $nodo = ($xp->query($xpath) ?: null)?->item(0);
        $this->assertInstanceOf(DOMElement::class, $nodo);

        return $nodo;
    }

    /** null quita el atributo */
    private function modificar(string $xpath, string $atributo, ?string $valor): string
    {
        $dom = $this->dom();
        $elemento = $this->elemento($this->xp($dom), $xpath);
        $valor === null ? $elemento->removeAttribute($atributo) : $elemento->setAttribute($atributo, $valor);

        return (string) $dom->saveXML();
    }

    /**
     * @param Violation[] $violaciones
     *
     * @return string[]
     */
    private function codigos(array $violaciones): array
    {
        return array_map(fn (Violation $v) => $v->code, $violaciones);
    }
}
