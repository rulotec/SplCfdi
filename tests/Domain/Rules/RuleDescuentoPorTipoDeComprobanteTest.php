<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Rules\RuleDescuentoPorTipoDeComprobante;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleDescuentoPorTipoDeComprobanteTest extends TestCase
{
    use AssertsRules;

    #[DataProvider('tipos')]
    public function testRechazaDescuentoEnTrasladoYPago(string $tipo): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00', '0.160000', '1', '10.00')], Fixtures::receptorNominativo(), null, tipoDeComprobante: $tipo
        );

        $this->assertCodigos(['descuento-en-traslado-o-pago'], (new RuleDescuentoPorTipoDeComprobante())->check(Fixtures::calculado($c)));
    }

    public static function tipos(): array
    {
        return ['traslado' => ['T'], 'pago' => ['P']];
    }

    public function testAceptaTrasladoSinDescuento(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::conceptoSinImpuestos('100.00')], Fixtures::receptorNominativo(), null, tipoDeComprobante: 'T'
        );

        $this->assertCodigos([], (new RuleDescuentoPorTipoDeComprobante())->check(Fixtures::calculado($c)));
    }

    public function testAceptaDescuentoEnUnIngreso(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('100.00', '0.160000', '1', '10.00')]);

        $this->assertCodigos([], (new RuleDescuentoPorTipoDeComprobante())->check(Fixtures::calculado($c)));
    }
}
