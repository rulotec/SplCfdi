<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Rules\RuleDescuentoNoMayorQueImporte;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleDescuentoNoMayorQueImporteTest extends TestCase
{
    use AssertsRules;

    public function testRechazaUnDescuentoMayorQueElImporte(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::concepto('20.00'),
            Fixtures::concepto('10.00', '0.160000', '1', '10.01'),
        ]);

        $violaciones = (new RuleDescuentoNoMayorQueImporte())->check(Fixtures::calculado($c));

        $this->assertCodigos(['descuento-mayor-que-importe'], $violaciones);
        $this->assertSame('Concepto[2].Descuento', $violaciones[0]->campo);
        $this->assertStringContainsString('Concepto 2 ("Prueba")', $violaciones[0]->message);
    }

    public function testAceptaUnDescuentoIgualAlImporte(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('10.00', '0.160000', '1', '10.00')]);

        $this->assertCodigos([], (new RuleDescuentoNoMayorQueImporte())->check(Fixtures::calculado($c)));
    }

    public function testAceptaConceptosSinDescuento(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('10.00')]);

        $this->assertCodigos([], (new RuleDescuentoNoMayorQueImporte())->check(Fixtures::calculado($c)));
    }
}
