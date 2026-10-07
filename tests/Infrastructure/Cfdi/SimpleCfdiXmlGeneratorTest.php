<?php
// tests/Infrastructure/Cfdi/SimpleCfdiXmlGeneratorTest.php
declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Cfdi;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Exceptions\XmlSchemaValidationException;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Services\{ImpuestoTrasladadoAggregator, InvoiceCalculator};
use SplCfdi\Infrastructure\Cfdi\SimpleCfdiXmlGenerator;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;
use SplCfdi\Tests\Support\Fixtures;

final class SimpleCfdiXmlGeneratorTest extends TestCase
{
    private const NS_CFDI = 'http://www.sat.gob.mx/cfd/4';

    private InvoiceCalculator $calculator;
    private SimpleCfdiXmlGenerator $generator;
    private LibXmlSchemaValidator $validator;

    protected function setUp(): void
    {
        $config = DecimalConfiguration::sat();
        $math = new BcMathDecimalMath($config);

        $this->calculator = new InvoiceCalculator($math, new ImpuestoTrasladadoAggregator($math), $config);
        $this->generator = new SimpleCfdiXmlGenerator();
        $this->validator = new LibXmlSchemaValidator();
    }

    public function testGeneraLosImportesDelComprobante(): void
    {
        $xp = $this->xpath([Fixtures::concepto('1000.00')]);

        $this->assertSame('1000.00', $xp->evaluate('string(/cfdi:Comprobante/@SubTotal)'));
        $this->assertSame('1160.00', $xp->evaluate('string(/cfdi:Comprobante/@Total)'));
        $this->assertSame('160.00', $xp->evaluate('string(/cfdi:Comprobante/cfdi:Impuestos/@TotalImpuestosTrasladados)'));
        $this->assertSame('AAA010101AAA', $xp->evaluate('string(/cfdi:Comprobante/cfdi:Emisor/@Rfc)'));
    }

    public function testLosHijosDelComprobanteVanEnElOrdenDelEsquema(): void
    {
        $xp = $this->xpath([Fixtures::concepto('1000.00')]);

        $nombres = [];
        foreach ($xp->query('/cfdi:Comprobante/*') as $nodo) {
            $nombres[] = $nodo->localName;
        }

        $this->assertSame(['Emisor', 'Receptor', 'Conceptos', 'Impuestos'], $nombres);
    }

    public function testCadaConceptoLlevaSusPropiosTraslados(): void
    {
        $xp = $this->xpath([
            Fixtures::concepto('100.00'), Fixtures::concepto('200.00'), Fixtures::concepto('300.00'),
        ]);

        $this->assertSame(3, (int) $xp->evaluate('count(//cfdi:Concepto)'));
        $this->assertSame(
            3,
            (int) $xp->evaluate('count(//cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado)')
        );
        // El resumen agrupa los tres en una sola línea.
        $this->assertSame(
            1,
            (int) $xp->evaluate('count(/cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado)')
        );
    }

    public function testElXmlGeneradoCumpleConElEsquema(): void
    {
        $xml = Fixtures::withSealPlaceholders($this->generar([
            Fixtures::concepto('1000.00'), Fixtures::concepto('0.05', '0.16'),
        ]));

        $this->validator->validate($xml);   // lanza excepción con la lista de errores si no es válido

        $this->addToAssertionCount(1);
    }

    /** El bug de los 12 decimales que corregimos: el XSD debe detectarlo. */
    public function testElXsdRechazaUnImporteConMasDeSeisDecimales(): void
    {
        $dom = new DOMDocument();
        $dom->loadXML(Fixtures::withSealPlaceholders($this->generar([Fixtures::concepto('1000.00')])));

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('cfdi', self::NS_CFDI);
        $xp->query('//cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado')
            ->item(0)
            ->setAttribute('Importe', '160.000000000000');

        $this->expectException(XmlSchemaValidationException::class);
        $this->validator->validate($dom->saveXML());
    }

    // ---- helpers ----

    /** @param Concepto[] $conceptos */
    private function generar(array $conceptos): string
    {
        return $this->generator->generate(
            $this->calculator->calculate(Fixtures::comprobante($conceptos))
        );
    }

    /** @param Concepto[] $conceptos */
    private function xpath(array $conceptos): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->loadXML($this->generar($conceptos));

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('cfdi', self::NS_CFDI);

        return $xp;
    }
}
