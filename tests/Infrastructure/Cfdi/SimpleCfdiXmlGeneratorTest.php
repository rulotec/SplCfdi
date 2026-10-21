<?php
declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Cfdi;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Models\CfdiRelacionados;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Models\ImpuestoTrasladado;
use SplCfdi\Domain\Models\ImpuestoRetenido;
use SplCfdi\Domain\Models\TipoFactor;
use SplCfdi\Domain\Services\InvoiceCalculator;
use SplCfdi\Infrastructure\Cfdi\SimpleCfdiXmlGenerator;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;
use SplCfdi\Tests\Support\AssertsCfdiSchema;
use SplCfdi\Tests\Support\Fixtures;

final class SimpleCfdiXmlGeneratorTest extends TestCase
{
    use AssertsCfdiSchema;

    private const NS_CFDI = 'http://www.sat.gob.mx/cfd/4';

    private InvoiceCalculator $calculator;
    private SimpleCfdiXmlGenerator $generator;

    protected function setUp(): void
    {
        $this->calculator = CfdiFactory::createCalculator();
        $this->generator = new SimpleCfdiXmlGenerator();
    }

    public function testGeneraLosImportesDelComprobante(): void
    {
        $xp = $this->xpath([Fixtures::concepto('1000.00')]);

        $this->assertSame('1000.00', $xp->evaluate('string(/cfdi:Comprobante/@SubTotal)'));
        $this->assertSame('1160.00', $xp->evaluate('string(/cfdi:Comprobante/@Total)'));
        $this->assertSame('160.00', $xp->evaluate('string(/cfdi:Comprobante/cfdi:Impuestos/@TotalImpuestosTrasladados)'));
        $this->assertSame('AAA010101AAA', $xp->evaluate('string(/cfdi:Comprobante/cfdi:Emisor/@Rfc)'));
        $this->assertSame('2026-10-01T12:00:00', $xp->evaluate('string(/cfdi:Comprobante/@Fecha)'));
    }

    public function testLosHijosDelComprobanteVanEnElOrdenDelEsquema(): void
    {
        $xp = $this->xpath([Fixtures::concepto('1000.00')]);

        $this->assertSame(
            ['InformacionGlobal', 'Emisor', 'Receptor', 'Conceptos', 'Impuestos'],
            $this->nombresDeHijos($xp, '/cfdi:Comprobante/*')
        );
    }

    public function testCadaConceptoLlevaSusPropiosTraslados(): void
    {
        $xp = $this->xpath([
            Fixtures::concepto('100.00'), Fixtures::concepto('200.00'), Fixtures::concepto('300.00'),
        ]);

        $this->assertSame(3, $this->contar($xp, '//cfdi:Concepto'));
        $this->assertSame(
            3,
            $this->contar($xp, '//cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado')
        );
        // El resumen agrupa los tres en una sola línea.
        $this->assertSame(
            1,
            $this->contar($xp, '/cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado')
        );
    }

    public function testElXmlGeneradoCumpleConElEsquema(): void
    {
        $this->assertCumpleConElEsquema($this->generar([
            Fixtures::concepto('1000.00'), Fixtures::concepto('0.05', '0.16'),
        ]));
    }

    /** El bug de los 12 decimales que corregimos: el XSD debe detectarlo. */
    public function testElXsdRechazaUnImporteConMasDeSeisDecimales(): void
    {
        $xp = $this->xpathDe($this->generar([Fixtures::concepto('1000.00')]));
        $this->elemento($xp, '//cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado')
            ->setAttribute('Importe', '160.000000000000');

        $violaciones = (new LibXmlSchemaValidator())->validate((string) $xp->document->saveXML());
        $this->assertSame(['Concepto[1].Traslado[1].Importe'], array_map(fn ($v) => $v->campo, $violaciones));
    }

    public function testElXmlConDescuentoCumpleConElEsquema(): void
    {
        $xml = $this->generar([Fixtures::concepto('1000.00', '0.160000', '1', '100.00')]);
        $xp = $this->xpathDe($xml);

        $this->assertSame('100.000000', $xp->evaluate('string(//cfdi:Concepto/@Descuento)'));
        $this->assertSame('100.00', $xp->evaluate('string(/cfdi:Comprobante/@Descuento)'));
        $this->assertSame('1044.00', $xp->evaluate('string(/cfdi:Comprobante/@Total)'));

        $this->assertCumpleConElEsquema($xml);
    }

    public function testElXmlConExentoYRetencionesCumpleConElEsquema(): void
    {
        $xml = $this->generar([
            Fixtures::concepto('1000.00', '0.160000', '1', null, [
                new ImpuestoRetenido('002', TipoFactor::Tasa, '0.106667'),
            ]),
            Fixtures::conceptoExento('50.00'),
        ]);
        $xp = $this->xpathDe($xml);

        // Orden del esquema: concepto → Traslados, Retenciones; comprobante → Retenciones, Traslados
        $this->assertSame(
            ['Traslados', 'Retenciones'],
            $this->nombresDeHijos($xp, '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto[1]/cfdi:Impuestos/*')
        );
        $this->assertSame(
            ['Retenciones', 'Traslados'],
            $this->nombresDeHijos($xp, '/cfdi:Comprobante/cfdi:Impuestos/*')
        );

        $this->assertSame('106.67', $xp->evaluate('string(/cfdi:Comprobante/cfdi:Impuestos/@TotalImpuestosRetenidos)'));
        $this->assertSame('1103.33', $xp->evaluate('string(/cfdi:Comprobante/@Total)'));   // 1050 + 160 − 106.67

        // Exento: sin TasaOCuota ni Importe (uno a nivel concepto y otro en el resumen)
        $this->assertSame(2, $this->contar($xp, "//cfdi:Traslado[@TipoFactor='Exento']"));
        $this->assertSame(0, $this->contar($xp, "//cfdi:Traslado[@TipoFactor='Exento'][@TasaOCuota or @Importe]"));

        $this->assertCumpleConElEsquema($xml);
    }

    public function testSinImpuestosNoSeGeneraElNodoImpuestos(): void
    {
        $xml = $this->generar([Fixtures::conceptoSinImpuestos('100.00')]);

        $this->assertSame(0, $this->contar($this->xpathDe($xml), '/cfdi:Comprobante/cfdi:Impuestos'));

        $this->assertCumpleConElEsquema($xml);
    }

    public function testGeneraLaInformacionGlobalDelPublicoEnGeneral(): void
    {
        $xml = $this->generar([Fixtures::concepto('100.00')]);
        $xp = $this->xpathDe($xml);

        $nodo = $this->elemento($xp, '/cfdi:Comprobante/cfdi:InformacionGlobal');

        $this->assertSame('01', $nodo->getAttribute('Periodicidad'));
        $this->assertSame('10', $nodo->getAttribute('Meses'));
        $this->assertSame('2026', $nodo->getAttribute('Año'));

        $this->assertCumpleConElEsquema($xml);
    }

    public function testSinInformacionGlobalNoSeGeneraElNodo(): void
    {
        $xml = $this->generator->generate(
            $this->calculator->calculate(Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')]))
            );

        $this->assertSame(0, $this->contar($this->xpathDe($xml), '/cfdi:Comprobante/cfdi:InformacionGlobal'));

        $this->assertCumpleConElEsquema($xml);
    }

    public function testGeneraTipoCambioYConfirmacion(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorNominativo(), null, 'USD',
            tipoCambio: '17.512300', confirmacion: 'Ab123'
        );
        $xml = $this->generator->generate($this->calculator->calculate($c));
        $xp = $this->xpathDe($xml);

        $this->assertSame('17.512300', $xp->evaluate('string(/cfdi:Comprobante/@TipoCambio)'));
        $this->assertSame('Ab123', $xp->evaluate('string(/cfdi:Comprobante/@Confirmacion)'));
        $this->assertCumpleConElEsquema($xml);
    }

    public function testGeneraLosCfdiRelacionadosDespuesDeInformacionGlobal(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorPublicoEnGeneral(), Fixtures::informacionGlobal(),
            tipoDeComprobante: 'E', metodoPago: 'PUE', formaPago: '01',
            cfdiRelacionados: [
                new CfdiRelacionados('01', ['5FB2822E-396D-4725-8521-CDC4BDD20CCF', '6F9A1B2C-1234-4ABC-9DEF-0123456789AB']),
                new CfdiRelacionados('04', ['0C1D2E3F-4A5B-4C6D-8E7F-9A0B1C2D3E4F']),
            ]
        );
        $xml = $this->generator->generate($this->calculator->calculate($c));
        $xp = $this->xpathDe($xml);

        $this->assertSame(
            ['InformacionGlobal', 'CfdiRelacionados', 'CfdiRelacionados', 'Emisor', 'Receptor', 'Conceptos', 'Impuestos'],
            $this->nombresDeHijos($xp, '/cfdi:Comprobante/*')
        );
        $this->assertSame('01', $xp->evaluate('string(/cfdi:Comprobante/cfdi:CfdiRelacionados[1]/@TipoRelacion)'));
        $this->assertSame(2, $this->contar($xp, '/cfdi:Comprobante/cfdi:CfdiRelacionados[1]/cfdi:CfdiRelacionado'));
        $this->assertCumpleConElEsquema($xml);
    }

    public function testGeneraIepsPorCuota(): void
    {
        $concepto = new Concepto(
            claveProdServ: '15101514', cantidad: '40', claveUnidad: 'LTR', unidad: 'Litro', descripcion: 'Gasolina',
            valorUnitario: '20.00', objetoImp: '02',
            impuestosTrasladados: [new ImpuestoTrasladado('003', TipoFactor::Cuota, '6.453000', '40')],
        );
        $xml = $this->generar([$concepto]);
        $traslado = $this->elemento($this->xpathDe($xml), '//cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');

        $this->assertSame(['40.000000', 'Cuota', '6.453000', '258.120000'], [
            $traslado->getAttribute('Base'), $traslado->getAttribute('TipoFactor'),
            $traslado->getAttribute('TasaOCuota'), $traslado->getAttribute('Importe'),
        ]);
        $this->assertCumpleConElEsquema($xml);
    }

    // ---- helpers ----

    /** @param Concepto[] $conceptos */
    private function generar(array $conceptos): string
    {
        return $this->generator->generate(
            $this->calculator->calculate(Fixtures::comprobante($conceptos))
        );
    }

    /** @param Concepto[] $conceptos */
    private function xpath(array $conceptos): DOMXPath
    {
        return $this->xpathDe($this->generar($conceptos));
    }

    private function xpathDe(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('cfdi', self::NS_CFDI);

        return $xp;
    }

    /** @return string[] */
    private function nombresDeHijos(DOMXPath $xp, string $consulta): array
    {
        $nombres = [];
        foreach ($xp->query($consulta) ?: [] as $nodo) {
            $nombres[] = (string) $nodo->localName;
        }

        return $nombres;
    }

    private function contar(DOMXPath $xp, string $consulta): int
    {
        $total = $xp->evaluate("count($consulta)");
        $this->assertIsFloat($total);

        return (int) $total;
    }

    private function elemento(DOMXPath $xp, string $consulta): DOMElement
    {
        $nodo = $xp->query($consulta) ?: null;
        $elemento = $nodo?->item(0);
        $this->assertInstanceOf(DOMElement::class, $elemento, "No se encontró $consulta");

        return $elemento;
    }
}
