<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Rules\RuleMetodoPagoPueEnGlobal;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleMetodoPagoPueEnGlobalTest extends TestCase
{
    use AssertsRules;

    #[DataProvider('metodos')]
    public function testExigePueEnUnCfdiGlobal(?string $metodo, array $esperados): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorPublicoEnGeneral(), Fixtures::informacionGlobal(),
            metodoPago: $metodo, formaPago: '01',
        );

        $this->assertCodigos($esperados, (new RuleMetodoPagoPueEnGlobal())->check(Fixtures::calculado($c)));
    }

    public static function metodos(): array
    {
        return [
            'PUE'          => ['PUE', []],
            'PPD'          => ['PPD', ['metodo-pago-global']],
            'no informado' => [null, ['metodo-pago-global']],
        ];
    }

    public function testNoAplicaSinInformacionGlobal(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')]);   // sin MetodoPago

        $this->assertCodigos([], (new RuleMetodoPagoPueEnGlobal())->check(Fixtures::calculado($c)));
    }
}
