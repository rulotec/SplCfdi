<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{Comprobante, Receptor};
use SplCfdi\Domain\Rules\RuleRfcGenerico;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleRfcGenericoTest extends TestCase
{
    use AssertsRules;

    public function testAceptaElPublicoEnGeneral(): void
    {
        $this->assertCodigos([], $this->check(Fixtures::comprobante([Fixtures::concepto('100.00')])));
    }

    public function testAplicaAunqueNoSeaUnCfdiGlobal(): void
    {
        $receptor = new Receptor('XAXX010101000', 'JUAN PEREZ', '62000', '605', 'G03');
        $violaciones = $this->check(Fixtures::comprobanteCon([Fixtures::concepto('100.00')], $receptor, null));

        $this->assertCodigos(['rfc-generico-regimen', 'rfc-generico-uso-cfdi'], $violaciones);
        $this->assertSame(['Receptor.RegimenFiscalReceptor', 'Receptor.UsoCFDI'], array_map(fn ($v) => $v->campo, $violaciones));
    }

    public function testAplicaAlRfcGenericoExtranjero(): void
    {
        $receptor = new Receptor('XEXX010101000', 'FOREIGN CO', '62000', '601', 'S01');

        $this->assertCodigos(['rfc-generico-regimen'], $this->check(Fixtures::comprobanteCon([Fixtures::concepto('100.00')], $receptor, null)));
    }

    public function testEnUnCfdiDePagoElUsoEsCp01(): void
    {
        $receptor = new Receptor('XAXX010101000', 'PUBLICO EN GENERAL', '62000', '616', 'CP01');
        $pago = Fixtures::comprobanteCon([Fixtures::conceptoSinImpuestos('0')], $receptor, null, tipoDeComprobante: 'P');
        $ingreso = Fixtures::comprobanteCon([Fixtures::concepto('100.00')], $receptor, null);

        $this->assertCodigos([], $this->check($pago));
        $this->assertCodigos(['rfc-generico-uso-cfdi'], $this->check($ingreso));
    }

    public function testNoAplicaAUnRfcIdentificado(): void
    {
        $this->assertCodigos([], $this->check(Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')])));
    }

    /** @return Violation[] */
    private function check(Comprobante $c): array
    {
        return (new RuleRfcGenerico())->check(Fixtures::calculado($c));
    }
}
