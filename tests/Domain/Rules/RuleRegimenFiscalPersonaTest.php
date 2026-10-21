<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{Comprobante, Emisor, Receptor};
use SplCfdi\Domain\Rules\RuleRegimenFiscalPersona;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleRegimenFiscalPersonaTest extends TestCase
{
    use AssertsRules;

    public function testAceptaRegimenesDelTipoDePersona(): void
    {
        $this->assertCodigos([], $this->check(Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')])));
    }

    public function testRechazaUnRegimenDePersonaFisicaEnUnaMoral(): void
    {
        $violaciones = $this->check($this->comprobante(new Emisor('AAA010101AAA', 'EMPRESA', '605'), Fixtures::receptorNominativo()));

        $this->assertCodigos(['regimen-emisor-persona'], $violaciones);
        $this->assertSame('Emisor.RegimenFiscal', $violaciones[0]->campo);
        $this->assertStringContainsString('no aplica a una persona moral', $violaciones[0]->message);
    }

    public function testRechazaUnRegimenDePersonaMoralEnUnaFisica(): void
    {
        $receptor = new Receptor('PEPJ800101AB1', 'JUAN PEREZ', '42501', '601', 'G03');

        $this->assertCodigos(['regimen-receptor-persona'], $this->check($this->comprobante(null, $receptor)));
    }

    public function testElRfcConEnieCuentaComoUnCaracter(): void
    {
        // 12 caracteres (persona moral) aunque "Ñ" ocupe dos bytes
        $emisor = new Emisor('ÑAA010101AAA', 'EMPRESA', '601');

        $this->assertCodigos([], $this->check($this->comprobante($emisor, Fixtures::receptorNominativo())));
    }

    public function testAmbosTiposDePersonaPuedenUsarElRegimen626(): void
    {
        $fisica = new Emisor('PEPJ800101AB1', 'JUAN PEREZ', '626');
        $moral = new Emisor('AAA010101AAA', 'EMPRESA', '626');

        $this->assertCodigos([], $this->check($this->comprobante($fisica, Fixtures::receptorNominativo())));
        $this->assertCodigos([], $this->check($this->comprobante($moral, Fixtures::receptorNominativo())));
    }

    private function comprobante(?Emisor $emisor, Receptor $receptor): Comprobante
    {
        return Fixtures::comprobanteCon([Fixtures::concepto('100.00')], $receptor, null, emisor: $emisor);
    }

    /** @return Violation[] */
    private function check(Comprobante $c): array
    {
        return (new RuleRegimenFiscalPersona(Fixtures::catalogs()->regimenesFiscales))->check(Fixtures::calculado($c));
    }
}
