<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Application;

use PHPUnit\Framework\TestCase;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;
use SplCfdi\Tests\Support\Fixtures;

final class CfdiServiceTest extends TestCase
{
    public function testGeneraElXmlYElResultadoCalculado(): void
    {
        $cfdi = CfdiFactory::create()->generate(
            Fixtures::comprobante([Fixtures::concepto('1000.00')])
        );

        $this->assertSame('1160.00', $cfdi->calculado->importes->total);
        $this->assertStringContainsString('Total="1160.00"', $cfdi->xml);
    }

    public function testElResultadoCompletoCumpleConElEsquema(): void
    {
        $cfdi = CfdiFactory::create()->generate(
            Fixtures::comprobante([Fixtures::concepto('1000.00'), Fixtures::concepto('0.05')])
        );

        (new LibXmlSchemaValidator())->validate(Fixtures::withSealPlaceholders($cfdi->xml));

        $this->addToAssertionCount(1);
    }

    public function testPermiteUnaConfiguracionPropia(): void
    {
        $config = new DecimalConfiguration(calculationScale: 12, maximumScale: 6, conceptScale: 4);

        $cfdi = CfdiFactory::create($config)->generate(
            Fixtures::comprobante([Fixtures::concepto('100.00')])
        );

        // Traslado del concepto con 4 decimales; el resumen sigue en los de la moneda.
        $this->assertStringContainsString('Importe="16.0000"', $cfdi->xml);
        $this->assertSame('16.00', $cfdi->calculado->impuestos->totalImpuestosTrasladados);
    }
}
