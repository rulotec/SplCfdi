<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{Emisor, InformacionGlobal};
use SplCfdi\Domain\Rules\RuleBimestralSoloRif;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleBimestralSoloRifTest extends TestCase
{
    use AssertsRules;

    public function testRechazaBimestralFueraDelRif(): void
    {
        $this->assertCodigos(['bimestral-solo-rif'], $this->check('05', '601'));
    }

    public function testAceptaBimestralParaElRif(): void
    {
        $this->assertCodigos([], $this->check('05', '621'));
    }

    public function testOtrasPeriodicidadesNoDependenDelRegimen(): void
    {
        $this->assertCodigos([], $this->check('01', '601'));
        $this->assertCodigos([], $this->check('04', '612'));
    }

    public function testNoAplicaSinInformacionGlobal(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')]);

        $this->assertCodigos([], (new RuleBimestralSoloRif())->check(Fixtures::calculado($c)));
    }

    /** @return Violation[] */
    private function check(string $periodicidad, string $regimenEmisor): array
    {
        $meses = $periodicidad === '05' ? '13' : '10';
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorPublicoEnGeneral(),
            new InformacionGlobal($periodicidad, $meses, 2026),
            emisor: new Emisor('AAA010101AAA', 'EMPRESA DE PRUEBA', $regimenEmisor),
        );

        return (new RuleBimestralSoloRif())->check(Fixtures::calculado($c));
    }
}
