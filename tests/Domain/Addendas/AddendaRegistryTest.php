<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Addendas;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Addendas\{AddendaRegistry, CampoAddenda, NivelCampo, TipoCampo};
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Tests\Support\AddendaDePrueba;

final class AddendaRegistryTest extends TestCase
{
    private function standard(): AddendaRegistry
    {
        return AddendaRegistry::standard(new BcMathDecimalMath(DecimalConfiguration::sat()));
    }

    public function testIncluyeLasAddendasDeLaBiblioteca(): void
    {
        $this->assertSame(['IEX', 'MPNP', 'PPY', 'PSV', 'SANMINA'], array_keys($this->standard()->todas()));
    }

    /** El namespace de cada addenda es el targetNamespace de su XSD: así la reconoce AddendaSchemaValidator. */
    public function testCadaAddendaTraeSuXsdConSuNamespace(): void
    {
        $standard = $this->standard();
        foreach ($standard->todas() as $clave => $addenda) {
            $esquema = (string) $addenda->esquema();
            $this->assertFileExists($esquema, $clave);

            $xsd = new DOMDocument();
            $xsd->load($esquema);
            $this->assertSame($addenda->namespace(), $xsd->documentElement?->getAttribute('targetNamespace'), $clave);
            $this->assertSame($addenda, $standard->porNamespace($addenda->namespace()));
        }
    }

    public function testLosNombresDeCampoNoSeRepiten(): void
    {
        foreach ($this->standard()->todas() as $clave => $addenda) {
            $nombres = array_map(fn (CampoAddenda $c) => $c->nombre, $addenda->campos());
            $this->assertSame(array_unique($nombres), $nombres, $clave);
        }
    }

    public function testUnHostAgregaLaSuyaSinModificarElOriginal(): void
    {
        $standard = $this->standard();
        $conPrueba = $standard->con(new AddendaDePrueba());

        $this->assertNull($standard->get('PRUEBA'));
        $this->assertInstanceOf(AddendaDePrueba::class, $conPrueba->get('PRUEBA'));
        $this->assertSame($conPrueba->get('PRUEBA'), $conPrueba->porNamespace(AddendaDePrueba::NAMESPACE));
        $this->assertCount(6, $conPrueba->todas());
    }

    public function testRechazaDosAddendasConLaMismaClave(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AddendaRegistry(new AddendaDePrueba(), new AddendaDePrueba());
    }

    public function testLasOpcionesVanSoloConTipoOpcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CampoAddenda('x', 'X', NivelCampo::Cliente, TipoCampo::Texto, opciones: ['A' => 'a']);
    }
}
