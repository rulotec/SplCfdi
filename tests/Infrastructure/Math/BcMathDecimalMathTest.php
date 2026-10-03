<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Math;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;

final class BcMathDecimalMathTest extends TestCase
{
    private BcMathDecimalMath $math;

    protected function setUp(): void   // runs before every test
    {
        $this->math = new BcMathDecimalMath(DecimalConfiguration::sat());
    }

    #[DataProvider('redondeos')]
    public function testRound(string $valor, int $decimales, string $esperado): void
    {
        $this->assertSame($esperado, $this->math->round($valor, $decimales));
    }

    public static function redondeos(): array
    {
        return [
            'mitad hacia arriba'     => ['0.005', 2, '0.01'],
            'caso clásico de float'  => ['1.005', 2, '1.01'],
            'no llega a la mitad'    => ['2.344999', 2, '2.34'],
            'cero decimales'         => ['12.5', 0, '13'],   // el bug de decimals === 0
            'negativo'               => ['-1.005', 2, '-1.01'],
            'rellena ceros'          => ['1.1', 3, '1.100'],
            'acarreo'                => ['9.995', 2, '10.00'],
        ];
    }

    public function testRoundRechazaValoresInvalidos(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->math->round('abc', 2);
    }
}