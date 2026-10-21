<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\Receptor;
use SplCfdi\Domain\Rules\RuleRegimenUsoCfdi;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleRegimenUsoCfdiTest extends TestCase
{
    use AssertsRules;

    private const MORAL = 'EKU9003173C9';     // 12 caracteres
    private const FISICA = 'GOMC800101AB1';   // 13 caracteres

    #[DataProvider('casos')]
    public function testMatriz(string $rfc, string $regimen, string $uso, array $esperados): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], new Receptor($rfc, 'RECEPTOR', '62000', $regimen, $uso), null
        );

        $this->assertCodigos($esperados, (new RuleRegimenUsoCfdi())->check(Fixtures::calculado($c)));
    }

    public static function casos(): array
    {
        return [
            'moral 601 gastos en general'     => [self::MORAL, '601', 'G03', []],
            'física 612 gastos en general'    => [self::FISICA, '612', 'G03', []],
            'física 605 deducción personal'   => [self::FISICA, '605', 'D01', []],
            'física 605 nómina'               => [self::FISICA, '605', 'CN01', []],
            'público en general'              => ['XAXX010101000', '616', 'S01', []],
            'régimen no admitido para el uso' => [self::FISICA, '605', 'G03', ['uso-cfdi-regimen']],
            'deducción personal en moral'     => [self::MORAL, '601', 'D01', ['uso-cfdi-persona', 'uso-cfdi-regimen']],
            'donativo en moral 603'           => [self::MORAL, '603', 'D04', []],
            'nómina en moral'                 => [self::MORAL, '601', 'CN01', ['uso-cfdi-persona', 'uso-cfdi-regimen']],
            'uso fuera de la matriz'          => [self::FISICA, '612', 'ZZ9', []],   // lo reporta RuleCatalogos
        ];
    }
}
