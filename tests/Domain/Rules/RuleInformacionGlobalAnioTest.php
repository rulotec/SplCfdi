<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\InformacionGlobal;
use SplCfdi\Domain\Rules\RuleInformacionGlobalAnio;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleInformacionGlobalAnioTest extends TestCase
{
    use AssertsRules;

    // La Fecha de los comprobantes de prueba es 2026-10-01

    #[DataProvider('casos')]
    public function testAnioEnCursoOAnterior(int $anio, ?string $codigo): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorPublicoEnGeneral(), new InformacionGlobal('01', '10', $anio)
        );

        $this->assertCodigos($codigo === null ? [] : [$codigo], (new RuleInformacionGlobalAnio())->check(Fixtures::calculado($c)));
    }

    public static function casos(): array
    {
        return [
            'año en curso'    => [2026, null],
            'año anterior'    => [2025, null],
            'dos años atrás'  => [2024, 'informacion-global-anio'],
            'año futuro'      => [2027, 'informacion-global-anio'],
        ];
    }

    public function testNoAplicaSinInformacionGlobal(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')]);

        $this->assertCodigos([], (new RuleInformacionGlobalAnio())->check(Fixtures::calculado($c)));
    }
}
