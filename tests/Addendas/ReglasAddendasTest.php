<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Addendas;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Addendas\{AddendaRegistry, DatosAddenda};
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{Comprobante, Concepto, ImpuestoTrasladado, TipoFactor};
use SplCfdi\Domain\Rules\{RuleAddenda, Violation};
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Tests\Support\{AssertsRules, EjemplosAddenda, Fixtures};

/** Reglas propias de las addendas incluidas, además de las de cada campo. */
final class ReglasAddendasTest extends TestCase
{
    use AssertsRules;

    /** @param Concepto[]|null $conceptos */
    private static function con(
        Comprobante $c,
        ?string $tipoDeComprobante = null,
        ?array $conceptos = null,
        ?DatosAddenda $addenda = null,
        bool $sinFolio = false,
        ?string $folio = null,
    ): Comprobante {
        return new Comprobante(
            $c->version, $c->fecha, $c->moneda, $tipoDeComprobante ?? $c->tipoDeComprobante, $c->exportacion,
            $c->lugarExpedicion, $c->emisor, $c->receptor, $conceptos ?? $c->conceptos,
            serie: $c->serie, folio: $sinFolio ? null : ($folio ?? $c->folio), metodoPago: $c->metodoPago, formaPago: $c->formaPago,
            tipoCambio: $c->tipoCambio, addenda: $addenda ?? $c->addenda,
        );
    }

    /** @return Violation[] */
    private function check(Comprobante $c): array
    {
        $registry = AddendaRegistry::standard(new BcMathDecimalMath(DecimalConfiguration::sat()));

        return (new RuleAddenda($registry))->check(Fixtures::calculado($c));
    }

    public function testLosEjemplosCumplen(): void
    {
        foreach (EjemplosAddenda::todos() as $archivo => [$comprobante]) {
            $this->assertSame([], array_map(fn (Violation $v) => $v->code, $this->check($comprobante)), $archivo);
        }
    }

    public function testElTipoDeDocumentoSeDerivaDelTipoDeComprobanteOSeIndica(): void
    {
        $traslado = self::con(EjemplosAddenda::volkswagen(), tipoDeComprobante: 'T');

        $violaciones = $this->check($traslado);
        $this->assertCodigos(['addenda-tipo-documento'], $violaciones);
        $this->assertSame('Addenda.tipoDocumentoFiscal', $violaciones[0]->campo);

        $datos = (array) EjemplosAddenda::volkswagen()->addenda?->valores;
        $indicado = self::con($traslado, addenda: new DatosAddenda('PSV', [...$datos, 'tipoDocumentoFiscal' => 'CA'], [['posicion' => '1']]));
        $this->assertCodigos([], $this->check($indicado));
    }

    public function testPpyRequiereFolio(): void
    {
        $violaciones = $this->check(self::con(EjemplosAddenda::stellantis(), sinFolio: true));

        $this->assertCodigos(['addenda-requiere-folio'], $violaciones);
        $this->assertSame('Folio', $violaciones[0]->campo);
    }

    public function testPpyConOrdenDeCompraRequiereAmmendment(): void
    {
        $c = EjemplosAddenda::stellantis();
        $datos = new DatosAddenda('PPY', (array) $c->addenda?->valores, [['ordenCompra' => '62151682']]);

        $violaciones = $this->check(self::con($c, addenda: $datos));

        $this->assertCodigos(['addenda-requerido'], $violaciones);
        $this->assertSame('Addenda.Concepto[1].ammendment', $violaciones[0]->campo);
    }

    /** El XSD solo admite Requisicion y ReferenciaProveedor junto con la orden de compra: sin ella se perderían. */
    public function testIexConRequisicionOReferenciaRequiereOrdenDeCompra(): void
    {
        $c = EjemplosAddenda::americanAxle();
        $valores = array_diff_key((array) $c->addenda?->valores, ['ordenCompra' => true, 'requisicion' => true, 'referenciaProveedor' => true]);
        $datos = new DatosAddenda('IEX', $valores, [['referenciaProveedor' => '700001'], ['ordenCompra' => '900001', 'requisicion' => '800001']]);

        $violaciones = $this->check(self::con($c, addenda: $datos));

        $this->assertCodigos(['addenda-requerido'], $violaciones);
        $this->assertSame('Addenda.Concepto[1].ordenCompra', $violaciones[0]->campo);
        $this->assertStringContainsString('referencia del proveedor', $violaciones[0]->message);
    }

    public function testIexSinOrdenDeCompraNiSusReferenciasCumple(): void
    {
        $c = EjemplosAddenda::americanAxle();
        $valores = array_diff_key((array) $c->addenda?->valores, ['ordenCompra' => true, 'requisicion' => true, 'referenciaProveedor' => true]);

        $this->assertCodigos([], $this->check(self::con($c, addenda: new DatosAddenda('IEX', $valores))));
    }

    /** El XSD de Marelli solo admite MXN, USD y EUR. */
    public function testMarelliRechazaOtraMoneda(): void
    {
        $c = EjemplosAddenda::marelli();
        $enLibras = new Comprobante(
            $c->version, $c->fecha, 'GBP', $c->tipoDeComprobante, $c->exportacion, $c->lugarExpedicion, $c->emisor,
            $c->receptor, $c->conceptos, folio: $c->folio, metodoPago: $c->metodoPago, formaPago: $c->formaPago,
            tipoCambio: '23.10', addenda: $c->addenda,
        );

        $violaciones = $this->check($enLibras);

        $this->assertCodigos(['addenda-moneda'], $violaciones);
        $this->assertSame('Moneda', $violaciones[0]->campo);
    }

    public function testPpyRechazaUnaTasaDeIvaSinCodigoDeCargo(): void
    {
        $concepto = new Concepto('84111506', '1', 'H87', 'EA', 'Frontera', '100.00', '02', [new ImpuestoTrasladado('002', TipoFactor::Tasa, '0.080000')]);

        $this->assertCodigos(['addenda-tasa-iva'], $this->check(self::con(EjemplosAddenda::stellantis(), conceptos: [$concepto])));
    }

    public function testMpnpRequiereFolioDeHasta20Caracteres(): void
    {
        $this->assertCodigos(['addenda-requiere-folio'], $this->check(self::con(EjemplosAddenda::metalsa(), sinFolio: true)));

        $violaciones = $this->check(self::con(EjemplosAddenda::metalsa(), folio: str_repeat('1', 21)));
        $this->assertCodigos(['addenda-longitud'], $violaciones);
        $this->assertSame('Folio', $violaciones[0]->campo);
    }

    public function testMpnpRechazaOrdenesDeCompraDeMasDeOchoDigitos(): void
    {
        $c = EjemplosAddenda::metalsa();
        $datos = new DatosAddenda('MPNP', (array) $c->addenda?->valores, [
            [...((array) $c->addenda?->conceptos)[0], 'ordenCompra' => '123456789'],
        ]);

        $violaciones = $this->check(self::con($c, addenda: $datos));
        $this->assertCodigos(['addenda-patron'], $violaciones);
        $this->assertSame('Addenda.Concepto[1].ordenCompra', $violaciones[0]->campo);
    }
}
