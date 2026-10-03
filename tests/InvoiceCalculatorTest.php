<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Services;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{Comprobante, Concepto, Emisor, ImpuestoTrasladado, Moneda, Receptor};
use SplCfdi\Domain\Services\{ImpuestoTrasladadoAggregator, InvoiceCalculator};
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;

final class InvoiceCalculatorTest extends TestCase
{
    private InvoiceCalculator $calculator;

    protected function setUp(): void
    {
        $math = new BcMathDecimalMath(new DecimalConfiguration(12, 6));
        $this->calculator = new InvoiceCalculator($math, new ImpuestoTrasladadoAggregator($math));
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

    // ---- helpers ----
    private function concepto(string $importe, string $tasa = '0.160000'): Concepto
    {
        return new Concepto(
            claveProdServ: '81112100', cantidad: '1', claveUnidad: 'E48', unidad: 'Servicio',
            descripcion: 'Prueba', valorUnitario: $importe, importe: $importe, objetoImp: '02',
            impuestosTrasladados: [new ImpuestoTrasladado('002', 'Tasa', $tasa)],
        );
    }

    private function comprobante(array $conceptos, ?Moneda $moneda = null): Comprobante
    {
        return new Comprobante(
            version: '4.0', fecha: '2026-10-01T12:00:00', moneda: $moneda ?? Moneda::mxn(),
            tipoDeComprobante: 'I', exportacion: '01', lugarExpedicion: '62000',
            emisor: new Emisor('AAA010101AAA', 'EMPRESA DE PRUEBA', '601'),
            receptor: new Receptor('XAXX010101000', 'PUBLICO EN GENERAL', '62000', '616', 'S01'),
            conceptos: $conceptos,
        );
    }
}