<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Catalogs;

use PHPUnit\Framework\TestCase;
use SplCfdi\Infrastructure\Catalogs\CatalogExclusions;

final class CatalogExclusionsTest extends TestCase
{
    public function testQuitaSoloLosValoresExcluidosDeSuCatalogo(): void
    {
        $filtrado = (new CatalogExclusions(['c_A' => ['P01']]))->filter([
            'c_A' => ['G01', 'P01'],
            'c_B' => ['P01'],   // mismo valor en otro catálogo: se conserva
        ]);

        $this->assertSame(['G01'], $filtrado['c_A']);
        $this->assertSame(['P01'], $filtrado['c_B']);
    }

    public function testSinArchivoNoExcluyeNada(): void
    {
        $exclusions = CatalogExclusions::load('/ruta/que/no/existe');

        $this->assertSame([], $exclusions->all());
        $this->assertSame(['c_A' => ['01']], $exclusions->filter(['c_A' => ['01']]));
    }

    public function testCargaElArchivoDeExclusiones(): void
    {
        $dir = sys_get_temp_dir() . '/excl_' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/_excluded.php', "<?php\nreturn ['c_A' => ['X']];\n");

        try {
            $this->assertSame(['c_A' => ['X']], CatalogExclusions::load($dir)->all());
        } finally {
            @unlink($dir . '/_excluded.php');
            @rmdir($dir);
        }
    }
}
