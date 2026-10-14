<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Rules\RuleSinImpuestosEnTrasladoOPago;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleSinImpuestosEnTrasladoOPagoTest extends TestCase
{
    use AssertsRules;

    #[DataProvider('tipos')]
    public function testRechazaImpuestosEnTrasladoYPago(string $tipo): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorNominativo(), null, tipoDeComprobante: $tipo
        );

        $this->assertCodigos(['impuestos-en-traslado-o-pago'], (new RuleSinImpuestosEnTrasladoOPago())->check(Fixtures::calculado($c)));
    }

    public static function tipos(): array
    {
        return ['traslado' => ['T'], 'pago' => ['P']];
    }

    public function testAceptaUnTrasladoSinImpuestos(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::conceptoSinImpuestos('100.00')], Fixtures::receptorNominativo(), null, tipoDeComprobante: 'T'
        );

        $this->assertCodigos([], (new RuleSinImpuestosEnTrasladoOPago())->check(Fixtures::calculado($c)));
    }

    public function testAceptaImpuestosEnUnIngreso(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')]);

        $this->assertCodigos([], (new RuleSinImpuestosEnTrasladoOPago())->check(Fixtures::calculado($c)));
    }
}