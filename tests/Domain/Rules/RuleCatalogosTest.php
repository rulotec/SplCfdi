<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{Comprobante, Emisor, ImpuestoTrasladado, InformacionGlobal, Receptor, TipoFactor};
use SplCfdi\Domain\Rules\RuleCatalogos;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleCatalogosTest extends TestCase
{
    use AssertsRules;

    public function testAceptaUnComprobanteConValoresDeCatalogo(): void
    {
        $this->assertCodigos([], $this->check(Fixtures::comprobante([Fixtures::concepto('100.00')])));
    }

    public function testRechazaUnTipoDeComprobanteDesconocido(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorNominativo(), null, tipoDeComprobante: 'Z'
        );

        $this->assertCodigos(['catalogo-invalido'], $this->check($c));
    }

    public function testRechazaMetodoYFormaDePagoDesconocidos(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorNominativo(), null,
            metodoPago: 'XXX', formaPago: '07'
        );

        $this->assertCodigos(array_fill(0, 2, 'catalogo-invalido'), $this->check($c));
    }

    public function testNoExigeMetodoNiFormaDePago(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')]);   // ambos null

        $this->assertCodigos([], $this->check($c));
    }

    public function testRechazaUnRegimenFiscalDelEmisorDesconocido(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorNominativo(), null,
            emisor: new Emisor('AAA010101AAA', 'EMISOR', '999')
        );

        $this->assertCodigos(['catalogo-invalido'], $this->check($c));
    }

    public function testRechazaRegimenYUsoDelReceptorDesconocidos(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], new Receptor('EKU9003173C9', 'RECEPTOR', '62000', '999', 'ZZ9'), null
        );

        $this->assertCodigos(array_fill(0, 2, 'catalogo-invalido'), $this->check($c));
    }

    public function testRechazaUnObjetoImpDesconocido(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::conceptoConObjetoImp('100.00', '09')]);

        $violaciones = $this->check($c);
        $this->assertCodigos(['catalogo-invalido'], $violaciones);
        $this->assertSame('Concepto[1].ObjetoImp', $violaciones[0]->campo);
        $this->assertSame(
            'Concepto 1 ("Prueba objeto"): ObjetoImp "09" no existe en el catálogo c_ObjetoImp.',
            $violaciones[0]->message
        );
    }

    public function testRechazaUnImpuestoDesconocido(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::conceptoConObjetoImp('100.00', '02', [new ImpuestoTrasladado('999', TipoFactor::Tasa, '0.160000')]),
        ]);

        $this->assertCodigos(['catalogo-invalido'], $this->check($c));
    }

    public function testRechazaUnaPeriodicidadDesconocida(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorPublicoEnGeneral(), new InformacionGlobal('09', '10', 2026)
        );

        $this->assertCodigos(['catalogo-invalido'], $this->check($c));
    }

    /** @return Violation[] */
    private function check(Comprobante $comprobante): array
    {
        return (new RuleCatalogos(Fixtures::catalogs()))->check(Fixtures::calculado($comprobante));
    }
}
