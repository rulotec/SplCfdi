<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Rules\{Formato, Violation};

final class FormatoTest extends TestCase
{
    public function testLongitudDentroDeLosLimites(): void
    {
        $this->assertNull(Formato::longitud('abc', 'Folio', 'Folio', 'x-longitud', minimo: 3, maximo: 3));
        $this->assertNull(Formato::longitud('abc', 'Folio', 'Folio', 'x-longitud'));
    }

    public function testLongitudMayorQueElMaximo(): void
    {
        $this->assertEquals(
            new Violation('x-longitud', 'Addenda X: Folio "12345" tiene 5 caracteres; el máximo es 4.', 'Folio'),
            Formato::longitud('12345', 'Addenda X: Folio', 'Folio', 'x-longitud', maximo: 4),
        );
    }

    public function testLongitudMenorQueElMinimo(): void
    {
        $this->assertEquals(
            new Violation('x-longitud', 'Código "1" tiene 1 caracteres; el mínimo es 2.', 'Addenda.codigo'),
            Formato::longitud('1', 'Código', 'Addenda.codigo', 'x-longitud', minimo: 2),
        );
    }

    /** Cuenta caracteres, no bytes. */
    public function testLongitudCuentaCaracteres(): void
    {
        $this->assertNull(Formato::longitud('ÑÑÑÑ', 'Nombre', 'Nombre', 'x-longitud', maximo: 4));
    }
}
