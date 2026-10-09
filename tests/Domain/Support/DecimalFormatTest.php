<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Support\DecimalFormat;

final class DecimalFormatTest extends TestCase
{
    #[DataProvider('casos')]
    public function testMatches(string $valor, ?int $max, bool $negativo, bool $esperado): void
    {
        $this->assertSame($esperado, DecimalFormat::matches($valor, $max, $negativo));
    }

    public static function casos(): array
    {
        return [
            // [valor, máx. decimales, permite negativo, esperado]
            'entero'                    => ['10', 6, false, true],
            'cero'                      => ['0', 6, false, true],
            'seis decimales'            => ['0.123456', 6, false, true],
            'siete decimales'           => ['1.1234567', 6, false, false],
            'cero decimales permitidos' => ['12', 0, false, true],
            'decimales no permitidos'   => ['12.5', 0, false, false],
            'vacío'                     => ['', 6, false, false],
            'no numérico'               => ['abc', 6, false, false],
            'negativo no permitido'     => ['-1', 6, false, false],
            'punto final'               => ['1.', 6, false, false],
            'sin parte entera'          => ['.5', 6, false, false],
            'coma decimal'              => ['1,5', 6, false, false],
            'espacio inicial'           => [' 1', 6, false, false],
            'salto de línea final'      => ["1\n", 6, false, false],
            'signo más'                 => ['+1', 6, false, false],
            'negativo permitido'        => ['-1.5', 6, true, true],
            'doble signo'               => ['--1', 6, true, false],
            'sin límite de decimales'   => ['1.1234567890123', null, false, true],
        ];
    }

    public function testElMensajeIncluyeElNombreDelCampo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cantidad');

        DecimalFormat::assertValid('abc', 'Cantidad', 6);
    }

    public function testRechazaUnMaximoDeDecimalesNegativo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DecimalFormat::matches('1', -1);
    }
}
