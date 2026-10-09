<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Models;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\ImpuestoTrasladado;
use SplCfdi\Domain\Models\TipoFactor;

final class ImpuestoTrasladadoTest extends TestCase
{
    public function testRechazaTasaConMasDeSeisDecimales(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImpuestoTrasladado('002', TipoFactor::Tasa, '0.1600001');
    }

    public function testExentoNoAdmiteTasa(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImpuestoTrasladado('002', TipoFactor::Exento, '0.160000');
    }

    public function testLaTasaEsObligatoriaSiNoEsExento(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImpuestoTrasladado('002', TipoFactor::Tasa);
    }
}
