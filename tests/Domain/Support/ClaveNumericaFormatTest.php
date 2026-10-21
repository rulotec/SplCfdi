<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Support\ClaveNumericaFormat;

final class ClaveNumericaFormatTest extends TestCase
{
    #[DataProvider('codigosPostalesValidos')]
    public function testAceptaCodigosPostalesDeCincoDigitos(string $cp): void
    {
        $this->assertTrue(ClaveNumericaFormat::esCodigoPostal($cp));
    }

    public static function codigosPostalesValidos(): array
    {
        return ['normal' => ['62000'], 'cero a la izquierda' => ['01000']];
    }

    #[DataProvider('codigosPostalesInvalidos')]
    public function testRechazaCualquierOtroCodigoPostal(string $cp): void
    {
        $this->assertFalse(ClaveNumericaFormat::esCodigoPostal($cp));
    }

    public static function codigosPostalesInvalidos(): array
    {
        return [
            'cuatro dígitos (cero perdido)' => ['1000'],
            'seis dígitos'                  => ['620000'],
            'letras'                        => ['abcde'],
            'espacio'                       => [' 6200'],
            'salto de línea final'          => ["62000\n"],
            'decimal'                       => ['62000.0'],
            'vacío'                         => [''],
        ];
    }

    #[DataProvider('clavesProdServValidas')]
    public function testAceptaClaveProdServDeOchoDigitos(string $clave): void
    {
        $this->assertTrue(ClaveNumericaFormat::esClaveProdServ($clave));
    }

    public static function clavesProdServValidas(): array
    {
        return ['normal' => ['81112100'], 'cero a la izquierda' => ['01010101']];
    }

    #[DataProvider('clavesProdServInvalidas')]
    public function testRechazaCualquierOtraClaveProdServ(string $clave): void
    {
        $this->assertFalse(ClaveNumericaFormat::esClaveProdServ($clave));
    }

    public static function clavesProdServInvalidas(): array
    {
        return [
            'siete dígitos (cero perdido)' => ['1010101'],
            'nueve dígitos'                => ['811121000'],
            'letra'                        => ['8111210A'],
            'espacio'                      => [' 8111210'],
            'salto de línea final'         => ["81112100\n"],
            'decimal'                      => ['8111210.'],
            'vacía'                        => [''],
        ];
    }
}
