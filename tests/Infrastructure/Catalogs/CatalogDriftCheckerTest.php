<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Catalogs;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Domain\Catalogs\UsoCfdiMatrix;
use SplCfdi\Infrastructure\Catalogs\CatalogDriftChecker;

final class CatalogDriftCheckerTest extends TestCase
{
    private CatalogDriftChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new CatalogDriftChecker();
    }

    public function testSinDiferenciasNoDevuelveNada(): void
    {
        $this->assertSame([], $this->checker->compare(['c_A' => ['01', '02']], ['c_A' => ['02', '01']]));
    }

    public function testDetectaValoresNuevosYDadosDeBaja(): void
    {
        $drifts = $this->checker->compare(['c_A' => ['01', '02', '03']], ['c_A' => ['01', '04', '05']]);

        $this->assertCount(1, $drifts);
        $this->assertSame(['04', '05'], $drifts[0]->added);
        $this->assertSame(['02', '03'], $drifts[0]->removed);
        $this->assertFalse($drifts[0]->missingInXsd);
    }

    public function testNoConfundeCeroIzquierda(): void
    {
        $drifts = $this->checker->compare(['c_A' => ['01']], ['c_A' => ['1']]);

        $this->assertSame(['1'], $drifts[0]->added);
        $this->assertSame(['01'], $drifts[0]->removed);
    }

    public function testAvisaSiElTipoNoEstaEnElXsd(): void
    {
        $drifts = $this->checker->compare(['c_A' => ['01']], []);

        $this->assertTrue($drifts[0]->missingInXsd);
    }

    public function testLaMatrizCoherenteConElXsdNoTieneAvisos(): void
    {
        $xsd = [
            CatalogName::USO_CFDI => UsoCfdiMatrix::usos(),
            CatalogName::REGIMEN_FISCAL => UsoCfdiMatrix::regimenes(),
        ];

        $this->assertSame([], $this->checker->matrixIssues($xsd));
    }

    public function testAvisaDeUnUsoNuevoSinFilaEnLaMatriz(): void
    {
        $xsd = [CatalogName::USO_CFDI => [...UsoCfdiMatrix::usos(), 'X99']];

        $issues = $this->checker->matrixIssues($xsd);

        $this->assertCount(1, $issues);
        $this->assertStringContainsString('X99', $issues[0]);
    }

    public function testAvisaDeUnUsoYDeUnRegimenQueYaNoExisten(): void
    {
        $xsd = [
            CatalogName::USO_CFDI => array_values(array_diff(UsoCfdiMatrix::usos(), ['G01'])),
            CatalogName::REGIMEN_FISCAL => array_values(array_diff(UsoCfdiMatrix::regimenes(), ['626'])),
        ];

        $this->assertCount(2, $this->checker->matrixIssues($xsd));
    }
}
