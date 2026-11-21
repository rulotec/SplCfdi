<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Addendas;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Addendas\DatosAddenda;

final class DatosAddendaTest extends TestCase
{
    public function testLoQueFaltaSeTomaDelRespaldo(): void
    {
        $datos = (new DatosAddenda('PSV', ['remision' => 'R1', 'tipoMoneda' => ' '], [1 => ['nota' => 'N2']]))
            ->completadoCon(new DatosAddenda('PSV', ['tipoMoneda' => 'MXP', 'remision' => 'R0'], [0 => ['unidadMedida' => 'MON'], 1 => ['unidadMedida' => 'H87']]));

        $this->assertSame('R1', $datos->valor('remision'));
        $this->assertSame('MXP', $datos->valor('tipoMoneda'), 'en blanco cuenta como vacío');
        $this->assertSame('MON', $datos->valorConcepto(0, 'unidadMedida'));
        $this->assertSame('H87', $datos->valorConcepto(1, 'unidadMedida'));
        $this->assertSame('N2', $datos->valorConcepto(1, 'nota'));
    }

    /** Un dato de concepto capturado para toda la factura gana sobre el respaldo de cada concepto. */
    public function testLoCapturadoParaTodosLosConceptosGanaSobreElRespaldoDeCadaUno(): void
    {
        $datos = (new DatosAddenda('PSV', ['unidadMedida' => 'SER'], [1 => ['unidadMedida' => 'PZA']]))
            ->completadoCon(new DatosAddenda('PSV', [], [0 => ['unidadMedida' => 'MON'], 1 => ['unidadMedida' => 'H87']]));

        $this->assertSame('SER', $datos->valorConcepto(0, 'unidadMedida'));
        $this->assertSame('PZA', $datos->valorConcepto(1, 'unidadMedida'));
    }
}
