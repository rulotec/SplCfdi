<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Rules\RuleBaseNoCero;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleBaseNoCeroTest extends TestCase
{
    use AssertsRules;

    public function testRechazaUnDescuentoTotalConImpuestos(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('10.00', '0.160000', '1', '10.00')]);

        $this->assertCodigos(['base-cero'], (new RuleBaseNoCero())->check(Fixtures::calculado($c)));
    }

    public function testUnaViolacionPorConcepto(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::concepto('10.00', '0.160000', '1', '10.00'),
            Fixtures::concepto('20.00'),
            Fixtures::concepto('30.00', '0.160000', '1', '30.00'),
        ]);

        $this->assertCodigos(['base-cero', 'base-cero'], (new RuleBaseNoCero())->check(Fixtures::calculado($c)));
    }

    public function testAceptaUnDescuentoParcial(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('10.00', '0.160000', '1', '9.99')]);

        $this->assertCodigos([], (new RuleBaseNoCero())->check(Fixtures::calculado($c)));
    }

    public function testNoAplicaSinImpuestos(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::conceptoSinImpuestos('100.00')]);

        $this->assertCodigos([], (new RuleBaseNoCero())->check(Fixtures::calculado($c)));
    }
}
