<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Models;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{ImpuestoRetenido, TipoFactor};

final class ImpuestoRetenidoTest extends TestCase
{
    public function testNoAdmiteExento(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImpuestoRetenido('002', TipoFactor::Exento, '0.106667');
    }

    public function testRechazaTasaConMasDeSeisDecimales(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImpuestoRetenido('002', TipoFactor::Tasa, '0.1066671');
    }
}