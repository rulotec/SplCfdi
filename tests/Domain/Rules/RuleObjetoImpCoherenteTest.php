<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{ImpuestoTrasladado, TipoFactor};
use SplCfdi\Domain\Rules\RuleObjetoImpCoherente;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleObjetoImpCoherenteTest extends TestCase
{
    use AssertsRules;

    public function testAceptaLosCasosCoherentes(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::concepto('100.00'),            // 02 con traslado
            Fixtures::conceptoExento('50.00'),       // 02 con Exento
            Fixtures::conceptoSinImpuestos('10.00'), // 01 sin impuestos
        ]);

        $this->assertCodigos([], $this->check($c));
    }

    public function testObjetoImp01NoAdmiteImpuestos(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::conceptoConObjetoImp('100.00', '01', [new ImpuestoTrasladado('002', TipoFactor::Tasa, '0.160000')]),
        ]);

        $this->assertCodigos(['objeto-imp-01-con-impuestos'], $this->check($c));
    }

    public function testObjetoImp02ExigeImpuestos(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::conceptoConObjetoImp('100.00', '02')]);

        $this->assertCodigos(['objeto-imp-02-sin-impuestos'], $this->check($c));
    }

    public function testExentoExigeObjetoImp02(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::conceptoConObjetoImp('100.00', '03', [new ImpuestoTrasladado('002', TipoFactor::Exento)]),
        ]);

        $this->assertCodigos(['objeto-imp-exento'], $this->check($c));
    }

    private function check($comprobante): array
    {
        return (new RuleObjetoImpCoherente())->check(Fixtures::calculado($comprobante));
    }
}
