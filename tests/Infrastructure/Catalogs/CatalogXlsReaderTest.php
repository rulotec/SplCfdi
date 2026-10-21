<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Catalogs;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SplCfdi\Infrastructure\Catalogs\CatalogXlsReader;

/** CSV con la misma forma que deja LibreOffice al convertir catCFDI.xls (encabezados de versión incluidos). */
final class CatalogXlsReaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/xls-reader-' . bin2hex(random_bytes(4));
        mkdir($this->dir);

        $this->hoja('c_Moneda', <<<CSV
            Catálogo de moneda. ,,,,,
            Versión CFDI,Versión catálogo,Revisión catálogo,Fecha publicación de catálogo,Fecha  inicio de vigencia del catálogo,Fecha fin de vigencia del catálogo
            4,1,1,0,01/01/2022,
            c_Moneda,Descripción,Decimales,Porcentaje variación,Fecha inicio de vigencia,Fecha fin de vigencia
            JPY,Yen,0,500%,01/01/2022,
            USD,Dólar americano,2,35%,01/01/2022,
            XXX,Sin moneda,0,,01/01/2022,
            OLD,Retirada,2,500%,01/01/2022,12/31/2023
            CSV);
        $this->hoja('c_RegimenFiscal', <<<CSV
            Catálogo de Régimen Fiscal.,,,,,
            ,,Aplica para tipo persona,,,
            c_RegimenFiscal,Descripción,Física,Moral,Fecha de inicio de vigencia,Fecha de fin de vigencia
            601,General de Ley Personas Morales,No,Sí,01/01/2022,
            626,Régimen Simplificado de Confianza,Sí,Sí,01/01/2022,
            CSV);
        $this->hoja('c_TasaOCuota', <<<CSV
            Catálogo de tasas o cuotas de impuestos.,,,,,,,,
            Rango o Fijo,c_TasaOCuota,,Impuesto,Factor,Traslado,Retención,Fecha inicio de vigencia,Fecha fin de vigencia
            ,Valor mínimo,Valor máximo,,,,,,
            Fijo,,0.16,IVA,Tasa,Sí,No,01/01/2022,
            Rango,0,0.16,IVA,Tasa,No,Sí,01/01/2022,
            Rango,0,72.1605,IEPS,Cuota,Sí,No,01/01/2026,
            Fijo,,0.08,IVA Crédito aplicado del 50%,Tasa,Sí,No,01/01/2022,
            CSV);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*") ?: []);
        rmdir($this->dir);
    }

    public function testLeeDecimalesYVariacionDeLasMonedasVigentes(): void
    {
        $monedas = $this->reader()->monedas();

        $this->assertSame(['JPY', 'USD', 'XXX'], array_keys($monedas));
        $this->assertSame(['descripcion' => 'Yen', 'decimales' => 0, 'variacion' => '5.0000'], $monedas['JPY']);
        $this->assertSame('0.3500', $monedas['USD']['variacion']);
        $this->assertNull($monedas['XXX']['variacion']);
    }

    public function testLeeElTipoDePersonaDeCadaRegimen(): void
    {
        $regimenes = $this->reader()->regimenesFiscales();

        $this->assertSame(['descripcion' => 'General de Ley Personas Morales', 'fisica' => false, 'moral' => true], $regimenes['601']);
        $this->assertSame(['descripcion' => 'Régimen Simplificado de Confianza', 'fisica' => true, 'moral' => true], $regimenes['626']);
    }

    public function testLeeValoresFijosYRangosConSuVigencia(): void
    {
        $tasas = $this->reader()->tasasOCuotas();

        $this->assertCount(4, $tasas);
        $this->assertSame(
            ['impuesto' => '002', 'factor' => 'Tasa', 'minimo' => null, 'maximo' => '0.160000', 'traslado' => true,
                'retencion' => false, 'inicio' => '2022-01-01', 'fin' => null],
            $tasas[0]
        );
        $this->assertSame(['003', '0.000000', '72.160500', '2026-01-01'], [$tasas[2]['impuesto'], $tasas[2]['minimo'], $tasas[2]['maximo'], $tasas[2]['inicio']]);
        $this->assertSame('002', $tasas[3]['impuesto'], 'el impuesto es la primera palabra del nombre');
    }

    public function testFallaConUnMensajeUtilSiFaltaUnaHoja(): void
    {
        $this->expectExceptionMessage('No se encontró la hoja c_Moneda');
        (new CatalogXlsReader('/no/existe', new DateTimeImmutable('2026-10-20')))->monedas();
    }

    private function reader(): CatalogXlsReader
    {
        return new CatalogXlsReader($this->dir, new DateTimeImmutable('2026-10-20'));
    }

    private function hoja(string $nombre, string $csv): void
    {
        file_put_contents("{$this->dir}/catCFDI-$nombre.csv", $csv . "\n");
    }
}
