<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Models;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\InformacionGlobal;

final class InformacionGlobalTest extends TestCase
{
    #[DataProvider('validos')]
    public function testAceptaCombinacionesCoherentes(string $periodicidad, string $meses): void
    {
        $info = new InformacionGlobal($periodicidad, $meses, 2026);

        $this->assertSame($meses, $info->meses);
    }

    public static function validos(): array
    {
        return [
            'diario'                   => ['01', '10'],
            'mensual, diciembre'       => ['04', '12'],
            'bimestral, primer bimestre' => ['05', '13'],
            'bimestral, último'        => ['05', '18'],
        ];
    }

    #[DataProvider('incoherentes')]
    public function testRechazaCombinacionesIncoherentes(string $periodicidad, string $meses, int $anio): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new InformacionGlobal($periodicidad, $meses, $anio);
    }

    public static function incoherentes(): array
    {
        return [
            'bimestral con mes simple'  => ['05', '10', 2026],
            'mensual con mes bimestral' => ['04', '13', 2026],
            'mes cero'                  => ['04', '00', 2026],
            'mes sin cero a la izquierda' => ['04', '1', 2026],
            'año de dos dígitos'        => ['04', '10', 26],
        ];
    }
}
