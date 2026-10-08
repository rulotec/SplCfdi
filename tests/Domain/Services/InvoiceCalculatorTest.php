<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Services;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{Comprobante, Concepto, Emisor, ImpuestoTrasladado, Moneda, Receptor};
use SplCfdi\Domain\Services\{ImpuestoTrasladadoAggregator, InvoiceCalculator};
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Tests\Support\Fixtures;

final class InvoiceCalculatorTest extends TestCase
{
    private InvoiceCalculator $calculator;

    protected function setUp(): void
    {
        $config = DecimalConfiguration::sat();
        $math = new BcMathDecimalMath($config);
        $this->calculator = new InvoiceCalculator($math, new ImpuestoTrasladadoAggregator($math), $config);
    }

    public function testConceptoSimpleConIva(): void
    {
        $r = $this->calculator->calculate($this->comprobante([$this->concepto('1000.00')]));

        $this->assertSame('1000.00', $r->importes->subTotal);
        $this->assertSame('160.00', $r->impuestos->totalImpuestosTrasladados);
        $this->assertSame('1160.00', $r->importes->total);
        $this->assertCount(1, $r->impuestos->traslados);
    }

    /** SAT: se redondea al final, no por concepto. */
    public function testRedondeaLaSumaYNoCadaConcepto(): void
    {
        $r = $this->calculator->calculate($this->comprobante([
            $this->concepto('0.05'), $this->concepto('0.05'), $this->concepto('0.05'),
        ]));

        // 3 × 0.008 = 0.024 → 0.02  (redondear antes daría 0.03)
        $this->assertSame('0.02', $r->impuestos->traslados[0]->importe);
        $this->assertSame('0.02', $r->impuestos->totalImpuestosTrasladados);
        $this->assertSame('0.15', $r->importes->subTotal);
        $this->assertSame('0.17', $r->importes->total);
    }

    public function testAgrupaPorTasa(): void
    {
        $r = $this->calculator->calculate($this->comprobante([
            $this->concepto('100.00', '0.160000'),
            $this->concepto('200.00', '0.160000'),
            $this->concepto('100.00', '0.000000'),
        ]));

        $this->assertCount(2, $r->impuestos->traslados);
        $this->assertSame('48.00', $r->impuestos->totalImpuestosTrasladados);
    }

    public function testRespetaLosDecimalesDeLaMoneda(): void
    {
        $r = $this->calculator->calculate(
            $this->comprobante([$this->concepto('1000')], new Moneda('JPY', 0))
        );

        $this->assertSame('1000', $r->importes->subTotal);
        $this->assertSame('160', $r->impuestos->totalImpuestosTrasladados);
    }

    public function testCalcularDosVecesDaElMismoResultado(): void
    {
        $c = $this->comprobante([$this->concepto('1000.00')]);

        $this->assertEquals($this->calculator->calculate($c), $this->calculator->calculate($c));
    }

    /** SAT: TotalImpuestosTrasladados = suma de los importes redondeados del resumen. */
    public function testTotalEsLaSumaDeLosImportesRedondeadosDelResumen(): void
    {
        // 0.025 al 16% = 0.004 por concepto; dos tasas distintas → dos grupos
        $r = $this->calculator->calculate($this->comprobante([
            $this->concepto('0.025', '0.160000'),
            $this->concepto('0.025', '0.160000'),   // mismo grupo: 0.008 → 0.01
            $this->concepto('0.025', '0.100000'),   // otro grupo: 0.0025 → 0.00
        ]));

        $this->assertCount(2, $r->impuestos->traslados);
        $this->assertSame('0.01', $r->impuestos->totalImpuestosTrasladados);
    }

    public function testCadaConceptoSoloTieneSusPropiosTraslados(): void
    {
        $r = $this->calculator->calculate($this->comprobante([
            $this->concepto('100.00'), $this->concepto('200.00'), $this->concepto('300.00'),
        ]));

        foreach ($r->conceptos as $c) {
            $this->assertCount(1, $c->traslados);
        }
        $this->assertSame('32.000000', $r->conceptos[1]->traslados[0]->importe);
    }

    public function testUsaLaEscalaDeConceptoConfigurada(): void
    {
        $config = new DecimalConfiguration(calculationScale: 12, maximumScale: 6, conceptScale: 4);
        $math = new BcMathDecimalMath($config);
        $calculator = new InvoiceCalculator($math, new ImpuestoTrasladadoAggregator($math), $config);

        $r = $calculator->calculate($this->comprobante([$this->concepto('100.00')]));

        $this->assertSame('16.0000', $r->conceptos[0]->traslados[0]->importe);
    }

    public function testNormalizaYAgrupaLaTasaConDistintoFormato(): void
    {
        $r = $this->calculator->calculate($this->comprobante([
            $this->concepto('100.00', '0.16'),
            $this->concepto('100.00', '0.160000'),
        ]));

        $this->assertCount(1, $r->impuestos->traslados);
        $this->assertSame('0.160000', $r->impuestos->traslados[0]->tasaOCuota);
    }

    public function testElImporteEsCantidadPorValorUnitario(): void
    {
        $r = $this->calculator->calculate($this->comprobante([
            Fixtures::concepto('10.00', '0.160000', '3'),
        ]));

        $this->assertSame('30.000000', $r->conceptos[0]->importe);
        $this->assertSame('30.00', $r->importes->subTotal);
        $this->assertSame('4.80', $r->impuestos->totalImpuestosTrasladados);
    }

    /** 1.5 × 0.333333 = 0.4999995 → a 6 decimales 0.500000; al final, 0.50. */
    public function testElImporteSeRedondeaASeisDecimales(): void
    {
        $r = $this->calculator->calculate($this->comprobante([
            Fixtures::concepto('0.333333', '0.160000', '1.5'),
        ]));

        $this->assertSame('0.500000', $r->conceptos[0]->importe);
        $this->assertSame('0.50', $r->importes->subTotal);
    }

    // ---- helpers ----
    private function concepto(string $importe, string $tasa = '0.160000'): Concepto
    {
        return Fixtures::concepto($importe, $tasa);
    }

    private function comprobante(array $conceptos, ?Moneda $moneda = null): Comprobante
    {
        return Fixtures::comprobante($conceptos, $moneda);
    }
}