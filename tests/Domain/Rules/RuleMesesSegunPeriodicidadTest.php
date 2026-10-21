<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\InformacionGlobal;
use SplCfdi\Domain\Rules\RuleMesesSegunPeriodicidad;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleMesesSegunPeriodicidadTest extends TestCase
{
    use AssertsRules;

    /** @param string[] $esperados */
    #[DataProvider('casos')]
    public function testMesesSegunLaPeriodicidad(string $periodicidad, string $meses, array $esperados): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('10.00')], Fixtures::receptorPublicoEnGeneral(), new InformacionGlobal($periodicidad, $meses, 2026)
        );

        $this->assertCodigos($esperados, (new RuleMesesSegunPeriodicidad())->check(Fixtures::calculado($c)));
    }

    public static function casos(): array
    {
        return [
            'diario'                     => ['01', '10', []],
            'mensual, diciembre'         => ['04', '12', []],
            'bimestral, primer bimestre' => ['05', '13', []],
            'bimestral, último'          => ['05', '18', []],
            'bimestral con mes simple'   => ['05', '10', ['meses-periodicidad']],
            'mensual con bimestre'       => ['04', '13', ['meses-periodicidad']],
            'mes cero'                   => ['04', '00', ['meses-periodicidad']],
        ];
    }

    public function testSinInformacionGlobalNoAplica(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('10.00')]);

        $this->assertCodigos([], (new RuleMesesSegunPeriodicidad())->check(Fixtures::calculado($c)));
    }
}
