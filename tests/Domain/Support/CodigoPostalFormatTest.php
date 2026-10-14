<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Support\CodigoPostalFormat;

final class CodigoPostalFormatTest extends TestCase
{
    #[DataProvider('validos')]
    public function testAceptaCincoDigitos(string $cp): void
    {
        CodigoPostalFormat::assertValid($cp, 'CP');

        $this->addToAssertionCount(1);
    }

    public static function validos(): array
    {
        return [
            'normal'          => ['62000'],
            'cero a la izquierda' => ['01000'],
        ];
    }

    #[DataProvider('invalidos')]
    public function testRechazaCualquierOtraCosa(string $cp): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CodigoPostalFormat::assertValid($cp, 'CP');
    }

    public static function invalidos(): array
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

    public function testElMensajeIncluyeElNombreDelCampo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LugarExpedicion');

        CodigoPostalFormat::assertValid('1000', 'LugarExpedicion');
    }
}
