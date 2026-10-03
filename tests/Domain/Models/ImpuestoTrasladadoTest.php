<?php

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\ImpuestoTrasladado;

// tests/Domain/Models/ImpuestoTrasladadoTest.php
final class ImpuestoTrasladadoTest extends TestCase
{
    public function testRechazaTasaConMasDeSeisDecimales(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImpuestoTrasladado('002', 'Tasa', '0.1600001');
    }
}
