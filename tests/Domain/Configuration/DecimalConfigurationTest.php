<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Configuration;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Configuration\DecimalConfiguration;

final class DecimalConfigurationTest extends TestCase
{
    public function testRechazaEscalaDeCalculoInsuficiente(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DecimalConfiguration(calculationScale: 6, maximumScale: 6, conceptScale: 6);
    }

    public function testRechazaEscalaDeConceptoMayorAlMaximo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DecimalConfiguration(calculationScale: 12, maximumScale: 6, conceptScale: 7);
    }
}