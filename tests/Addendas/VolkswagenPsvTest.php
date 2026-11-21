<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Addendas;

use PHPUnit\Framework\TestCase;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Addendas\DatosAddenda;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Tests\Support\EjemplosAddenda;

/** tipoMoneda de la addenda PSV: VW llama MXP al peso mexicano. */
final class VolkswagenPsvTest extends TestCase
{
    public function testElPesoMexicanoSeEnviaComoMxp(): void
    {
        $this->assertSame('MXP', $this->tipoMoneda(EjemplosAddenda::volkswagen()));
    }

    public function testOtraMonedaSeEnviaComoEnElCfdi(): void
    {
        $this->assertSame('USD', $this->tipoMoneda(self::con(EjemplosAddenda::volkswagen(), moneda: 'USD', tipoCambio: '17.50')));
    }

    public function testLaMonedaIndicadaTienePrioridad(): void
    {
        $ejemplo = EjemplosAddenda::volkswagen();
        $addenda = $ejemplo->addenda;
        $this->assertNotNull($addenda);

        $indicada = new DatosAddenda('PSV', [...$addenda->valores, 'tipoMoneda' => 'MXN'], $addenda->conceptos);

        $this->assertSame('MXN', $this->tipoMoneda(self::con($ejemplo, addenda: $indicada)));
    }

    private static function con(Comprobante $c, ?string $moneda = null, ?string $tipoCambio = null, ?DatosAddenda $addenda = null): Comprobante
    {
        return new Comprobante(
            $c->version, $c->fecha, $moneda ?? $c->moneda, $c->tipoDeComprobante, $c->exportacion,
            $c->lugarExpedicion, $c->emisor, $c->receptor, $c->conceptos,
            serie: $c->serie, folio: $c->folio, metodoPago: $c->metodoPago, formaPago: $c->formaPago,
            tipoCambio: $tipoCambio ?? $c->tipoCambio, addenda: $addenda ?? $c->addenda,
        );
    }

    private function tipoMoneda(Comprobante $comprobante): string
    {
        $xml = CfdiFactory::create(null, EjemplosAddenda::clock($comprobante))->draft($comprobante)->xml;
        $this->assertNotNull($xml);
        $this->assertSame(1, preg_match('/<PSV:Moneda [^>]*tipoMoneda="([^"]*)"/', $xml, $m), $xml);

        return $m[1];
    }
}
