<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Models;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{Comprobante, Emisor, Moneda, Receptor};

final class ComprobanteTest extends TestCase
{
    public function testRechazaUnLugarDeExpedicionInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LugarExpedicion');

        new Comprobante(
            version: '4.0', fecha: new DateTimeImmutable('2026-10-01T12:00:00'), moneda: Moneda::mxn(),
            tipoDeComprobante: 'I', exportacion: '01', lugarExpedicion: '6200',
            emisor: new Emisor('AAA010101AAA', 'EMPRESA DE PRUEBA', '601'),
            receptor: new Receptor('XAXX010101000', 'PUBLICO EN GENERAL', '62000', '616', 'S01'),
            conceptos: [],
        );
    }
}
