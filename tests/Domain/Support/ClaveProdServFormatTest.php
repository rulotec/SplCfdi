<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Support\ClaveProdServFormat;

final class ClaveProdServFormatTest extends TestCase
{
    #[DataProvider('validas')]
    public function testAceptaOchoDigitos(string $clave): void
    {
        ClaveProdServFormat::assertValid($clave, 'ClaveProdServ');

        $this->addToAssertionCount(1);
    }

    public static function validas(): array
    {
        return ['normal' => ['81112100'], 'cero a la izquierda' => ['01010101']];
    }

    #[DataProvider('invalidas')]
    public function testRechazaCualquierOtraCosa(string $clave): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ClaveProdServ');

        ClaveProdServFormat::assertValid($clave, 'ClaveProdServ');
    }

    public static function invalidas(): array
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
