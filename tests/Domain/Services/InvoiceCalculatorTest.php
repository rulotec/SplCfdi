<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Services;

use PHPUnit\Framework\TestCase;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{Comprobante, Concepto, ImpuestoRetenido, TipoFactor, Moneda};
use SplCfdi\Domain\Services\InvoiceCalculator;
use SplCfdi\Tests\Support\Fixtures;

final class InvoiceCalculatorTest extends TestCase
{
    private InvoiceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = CfdiFactory::createCalculator();
    }

    public function testConceptoSimpleConIva(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([Fixtures::concepto('1000.00')]));

        $this->assertSame('1000.00', $r->importes->subTotal);
        $this->assertSame('160.00', $r->impuestos->totalImpuestosTrasladados);
        $this->assertSame('1160.00', $r->importes->total);
        $this->assertCount(1, $r->impuestos->traslados);
    }

    /** SAT: se redondea al final, no por concepto. */
    public function testRedondeaLaSumaYNoCadaConcepto(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('0.05'), Fixtures::concepto('0.05'), Fixtures::concepto('0.05'),
        ]));

        // 3 × 0.008 = 0.024 → 0.02  (redondear antes daría 0.03)
        $this->assertSame('0.02', $r->impuestos->traslados[0]->importe);
        $this->assertSame('0.02', $r->impuestos->totalImpuestosTrasladados);
        $this->assertSame('0.15', $r->importes->subTotal);
        $this->assertSame('0.17', $r->importes->total);
    }

    public function testAgrupaPorTasa(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('100.00', '0.160000'),
            Fixtures::concepto('200.00', '0.160000'),
            Fixtures::concepto('100.00', '0.000000'),
        ]));

        $this->assertCount(2, $r->impuestos->traslados);
        $this->assertSame('48.00', $r->impuestos->totalImpuestosTrasladados);
    }

    public function testRespetaLosDecimalesDeLaMoneda(): void
    {
        $r = $this->calculator->calculate(
            Fixtures::comprobante([Fixtures::concepto('1000')], new Moneda('JPY', 0))
        );

        $this->assertSame('1000', $r->importes->subTotal);
        $this->assertSame('160', $r->impuestos->totalImpuestosTrasladados);
    }

    public function testCalcularDosVecesDaElMismoResultado(): void
    {
        $c = Fixtures::comprobante([Fixtures::concepto('1000.00')]);

        $this->assertEquals($this->calculator->calculate($c), $this->calculator->calculate($c));
    }

    /** SAT: TotalImpuestosTrasladados = suma de los importes redondeados del resumen. */
    public function testTotalEsLaSumaDeLosImportesRedondeadosDelResumen(): void
    {
        // 0.025 al 16% = 0.004 por concepto; dos tasas distintas → dos grupos
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('0.025', '0.160000'),
            Fixtures::concepto('0.025', '0.160000'),   // mismo grupo: 0.008 → 0.01
            Fixtures::concepto('0.025', '0.100000'),   // otro grupo: 0.0025 → 0.00
        ]));

        $this->assertCount(2, $r->impuestos->traslados);
        $this->assertSame('0.01', $r->impuestos->totalImpuestosTrasladados);
    }

    public function testCadaConceptoSoloTieneSusPropiosTraslados(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('100.00'), Fixtures::concepto('200.00'), Fixtures::concepto('300.00'),
        ]));

        foreach ($r->conceptos as $c) {
            $this->assertCount(1, $c->traslados);
        }
        $this->assertSame('32.000000', $r->conceptos[1]->traslados[0]->importe);
    }

    public function testUsaLaEscalaDeConceptoConfigurada(): void
    {
        $config = new DecimalConfiguration(calculationScale: 12, maximumScale: 6, conceptScale: 4);
        $calculator = CfdiFactory::createCalculator($config);

        $r = $calculator->calculate(Fixtures::comprobante([Fixtures::concepto('100.00')]));

        $this->assertSame('16.0000', $r->conceptos[0]->traslados[0]->importe);
    }

    public function testNormalizaYAgrupaLaTasaConDistintoFormato(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('100.00', '0.16'),
            Fixtures::concepto('100.00', '0.160000'),
        ]));

        $this->assertCount(1, $r->impuestos->traslados);
        $this->assertSame('0.160000', $r->impuestos->traslados[0]->tasaOCuota);
    }

    public function testElImporteEsCantidadPorValorUnitario(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('10.00', '0.160000', '3'),
        ]));

        $this->assertSame('30.000000', $r->conceptos[0]->importe);
        $this->assertSame('30.00', $r->importes->subTotal);
        $this->assertSame('4.80', $r->impuestos->totalImpuestosTrasladados);
    }

    /** 1.5 × 0.333333 = 0.4999995 → a 6 decimales 0.500000; al final, 0.50. */
    public function testElImporteSeRedondeaASeisDecimales(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('0.333333', '0.160000', '1.5'),
        ]));

        $this->assertSame('0.500000', $r->conceptos[0]->importe);
        $this->assertSame('0.50', $r->importes->subTotal);
    }

    public function testElDescuentoReduceLaBaseDelImpuestoYElTotal(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('1000.00', '0.160000', '1', '100.00'),
        ]));

        $this->assertSame('100.000000', $r->conceptos[0]->descuento);
        $this->assertSame('900.000000', $r->conceptos[0]->traslados[0]->base);
        $this->assertSame('900.00', $r->impuestos->traslados[0]->base);
        $this->assertSame('1000.00', $r->importes->subTotal);
        $this->assertSame('100.00', $r->importes->descuento);
        $this->assertSame('144.00', $r->impuestos->totalImpuestosTrasladados);
        $this->assertSame('1044.00', $r->importes->total);
    }

    public function testSinDescuentoNoSeGeneraDescuento(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([Fixtures::concepto('1000.00')]));

        $this->assertNull($r->importes->descuento);
        $this->assertNull($r->conceptos[0]->descuento);
    }

    /** 3 × 0.005 = 0.015 → 0.02 (redondear cada concepto daría 0.03). */
    public function testElDescuentoDelComprobanteEsLaSumaRedondeadaDeLosConceptos(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('1.00', '0.160000', '1', '0.005'),
            Fixtures::concepto('1.00', '0.160000', '1', '0.005'),
            Fixtures::concepto('1.00', '0.160000', '1', '0.005'),
        ]));

        $this->assertSame('0.02', $r->importes->descuento);
        $this->assertSame('2.99', $r->impuestos->traslados[0]->base);              // 3 × 0.995 = 2.985
        $this->assertSame('0.48', $r->impuestos->totalImpuestosTrasladados);       // 3 × 0.1592 = 0.4776
        $this->assertSame('3.46', $r->importes->total);                            // 3.00 − 0.02 + 0.48
    }

    public function testRechazaUnDescuentoMayorQueElImporte(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('10.00', '0.160000', '1', '10.01'),
        ]));
    }

    public function testConceptoExentoNoTieneTasaNiImporteNiTotalDeTraslados(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([Fixtures::conceptoExento('1000.00')]));

        $traslado = $r->impuestos->traslados[0];
        $this->assertCount(1, $r->impuestos->traslados);
        $this->assertSame(TipoFactor::Exento, $traslado->tipoFactor);
        $this->assertSame('1000.00', $traslado->base);
        $this->assertNull($traslado->tasaOCuota);
        $this->assertNull($traslado->importe);
        $this->assertNull($r->impuestos->totalImpuestosTrasladados);
        $this->assertSame('1000.00', $r->importes->total);
    }

    public function testExentoYGravadoConviven(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('100.00'),
            Fixtures::conceptoExento('50.00'),
        ]));

        $this->assertCount(2, $r->impuestos->traslados);
        $this->assertSame('100.00', $r->impuestos->traslados[0]->base);
        $this->assertSame('50.00', $r->impuestos->traslados[1]->base);
        $this->assertNull($r->impuestos->traslados[1]->importe);
        $this->assertSame('16.00', $r->impuestos->totalImpuestosTrasladados);
        $this->assertSame('166.00', $r->importes->total);
    }

    public function testSinImpuestosNoHayTotalesDeImpuestos(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([Fixtures::conceptoSinImpuestos('100.00')]));

        $this->assertSame([], $r->impuestos->traslados);
        $this->assertSame([], $r->impuestos->retenciones);
        $this->assertNull($r->impuestos->totalImpuestosTrasladados);
        $this->assertNull($r->impuestos->totalImpuestosRetenidos);
        $this->assertSame('100.00', $r->importes->total);
    }

    public function testLasRetencionesSeRestanDelTotal(): void
    {
        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('1000.00', '0.160000', '1', null, [
                new ImpuestoRetenido('002', TipoFactor::Tasa, '0.106667'),   // IVA
                new ImpuestoRetenido('001', TipoFactor::Tasa, '0.100000'),   // ISR
            ]),
        ]));

        $this->assertSame('106.667000', $r->conceptos[0]->retenciones[0]->importe);
        $this->assertCount(2, $r->impuestos->retenciones);
        $this->assertSame('106.67', $r->impuestos->retenciones[0]->importe);
        $this->assertSame('100.00', $r->impuestos->retenciones[1]->importe);
        $this->assertSame('206.67', $r->impuestos->totalImpuestosRetenidos);
        $this->assertSame('160.00', $r->impuestos->totalImpuestosTrasladados);
        $this->assertSame('953.33', $r->importes->total);   // 1000 + 160 − 206.67
    }

    /** 2 × 1.06667 = 2.13334 → 2.13 (redondear cada concepto daría 2.14). */
    public function testLasRetencionesSeRedondeanAlFinalYNoPorConcepto(): void
    {
        $retencion = [new ImpuestoRetenido('002', TipoFactor::Tasa, '0.106667')];

        $r = $this->calculator->calculate(Fixtures::comprobante([
            Fixtures::concepto('10.00', '0.160000', '1', null, $retencion),
            Fixtures::concepto('10.00', '0.160000', '1', null, $retencion),
        ]));

        $this->assertSame('2.13', $r->impuestos->totalImpuestosRetenidos);
        $this->assertSame('21.07', $r->importes->total);   // 20.00 + 3.20 − 2.13
    }
}