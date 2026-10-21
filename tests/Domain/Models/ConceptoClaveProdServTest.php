<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Models;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\Concepto;

final class ConceptoClaveProdServTest extends TestCase
{
    public function testRechazaUnaClaveProdServConMenosDeOchoDigitos(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ClaveProdServ');

        new Concepto('1010101', '1', 'E48', 'Servicio', 'Prueba', '10.00', '01', []);
    }

    public function testAceptaUnaClaveProdServConCeroAlaIzquierda(): void
    {
        $concepto = new Concepto('01010101', '1', 'E48', 'Servicio', 'Prueba', '10.00', '01', []);

        $this->assertSame('01010101', $concepto->claveProdServ);
    }
}
