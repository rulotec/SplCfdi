<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Application;

use PHPUnit\Framework\TestCase;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{Concepto, ImpuestoTrasladado, TipoFactor};
use SplCfdi\Tests\Support\AssertsCfdiSchema;
use SplCfdi\Tests\Support\Fixtures;

final class CfdiServiceTest extends TestCase
{
    use AssertsCfdiSchema;

    public function testGeneraElXmlYElResultadoCalculado(): void
    {
        $cfdi = CfdiFactory::create(null, Fixtures::clock())->generate(
            Fixtures::comprobante([Fixtures::concepto('1000.00')])
        );

        $this->assertSame('1160.00', $cfdi->calculado?->importes->total);
        $this->assertStringContainsString('Total="1160.00"', (string) $cfdi->xml);
    }

    public function testElResultadoCompletoCumpleConElEsquema(): void
    {
        $cfdi = CfdiFactory::create(null, Fixtures::clock())->generate(
            Fixtures::comprobante([Fixtures::concepto('1000.00'), Fixtures::concepto('0.05')])
        );

        $this->assertCumpleConElEsquema((string) $cfdi->xml);
    }

    public function testPermiteUnaConfiguracionPropia(): void
    {
        $config = new DecimalConfiguration(calculationScale: 12, maximumScale: 6, conceptScale: 4);

        $cfdi = CfdiFactory::create($config, Fixtures::clock())->generate(
            Fixtures::comprobante([Fixtures::concepto('100.00')])
        );

        // Traslado del concepto con 4 decimales; el resumen sigue en los de la moneda.
        $this->assertStringContainsString('Importe="16.0000"', (string) $cfdi->xml);
        $this->assertSame('16.00', $cfdi->calculado?->impuestos->totalImpuestosTrasladados);
    }

    public function testDevuelveLasViolacionesDeReglasConElCalculoYSinXml(): void
    {
        // Público en general sin InformacionGlobal
        $comprobante = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorPublicoEnGeneral(), null
        );

        $resultado = CfdiFactory::create(null, Fixtures::clock())->generate($comprobante);

        $this->assertFalse($resultado->isValid());
        $this->assertSame(['informacion-global-requerida'], array_map(fn ($v) => $v->code, $resultado->violations));
        $this->assertNotNull($resultado->calculado);
        $this->assertNull($resultado->xml);
    }

    public function testConErroresDeFormatoNoCalculaYLosReportaTodos(): void
    {
        $comprobante = Fixtures::comprobante([
            Fixtures::concepto('1,000.00'),
            Fixtures::concepto('10.00', '0.160000', 'abc'),
            Fixtures::conceptoConObjetoImp('10.00', '02', [new ImpuestoTrasladado('002', TipoFactor::Tasa)]),
        ]);

        $resultado = CfdiFactory::create(null, Fixtures::clock())->generate($comprobante);

        $this->assertSame(
            ['Concepto[1].ValorUnitario', 'Concepto[2].Cantidad', 'Concepto[3].Traslado[1].TasaOCuota'],
            array_map(fn ($v) => $v->campo, $resultado->violations)
        );
        $this->assertNull($resultado->calculado);
        $this->assertNull($resultado->xml);
    }

    public function testValidateNoGeneraXml(): void
    {
        $resultado = CfdiFactory::create(null, Fixtures::clock())->validate(
            Fixtures::comprobante([Fixtures::concepto('1000.00')])
        );

        $this->assertTrue($resultado->isValid());
        $this->assertSame('1160.00', $resultado->calculado?->importes->total);
        $this->assertNull($resultado->xml);
    }

    public function testIncluyeLosErroresDelEsquemaQueNingunaReglaRevisa(): void
    {
        $resultado = CfdiFactory::create(null, Fixtures::clock())->generate(
            Fixtures::comprobante([$this->concepto(unidad: str_repeat('x', 21))])
        );

        $this->assertSame(['xsd-longitud'], array_map(fn ($v) => $v->code, $resultado->violations));
        $this->assertSame('Concepto[1].Unidad', $resultado->violations[0]->campo);
        $this->assertNotNull($resultado->calculado);
        $this->assertNull($resultado->xml);
    }

    public function testValidateTambienRevisaElEsquema(): void
    {
        $resultado = CfdiFactory::create(null, Fixtures::clock())->validate(
            Fixtures::comprobante([$this->concepto(unidad: str_repeat('x', 21))])
        );

        $this->assertSame(['xsd-longitud'], array_map(fn ($v) => $v->code, $resultado->violations));
        $this->assertNull($resultado->xml);
    }

    public function testNoRepiteElErrorDelEsquemaDeUnCampoQueYaReportoUnaRegla(): void
    {
        // ClaveUnidad "E": RuleCatalogos (catalogo-invalido) y el patrón del XSD fallan en el mismo campo
        $resultado = CfdiFactory::create(null, Fixtures::clock())->generate(
            Fixtures::comprobante([$this->concepto(claveUnidad: 'E')])
        );

        $this->assertSame(['catalogo-invalido'], array_map(fn ($v) => $v->code, $resultado->violations));
    }

    public function testSePuedeOmitirLaValidacionXsd(): void
    {
        $resultado = CfdiFactory::create(null, Fixtures::clock(), validarXsd: false)->generate(
            Fixtures::comprobante([$this->concepto(unidad: str_repeat('x', 21))])
        );

        $this->assertTrue($resultado->isValid());
        $this->assertNotNull($resultado->xml);
    }

    private function concepto(string $unidad = 'Servicio', string $claveUnidad = 'E48'): Concepto
    {
        return new Concepto(
            claveProdServ: '81112100', cantidad: '1', claveUnidad: $claveUnidad, unidad: $unidad, descripcion: 'Prueba',
            valorUnitario: '100.00', objetoImp: '02',
            impuestosTrasladados: [new ImpuestoTrasladado('002', TipoFactor::Tasa, '0.160000')],
        );
    }
}
