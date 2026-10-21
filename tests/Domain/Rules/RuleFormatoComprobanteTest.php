<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{CfdiRelacionados, InformacionGlobal, Receptor};
use SplCfdi\Domain\Rules\RuleFormatoComprobante;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleFormatoComprobanteTest extends TestCase
{
    use AssertsRules;

    public function testAceptaUnComprobanteBienFormado(): void
    {
        $this->assertCodigos([], $this->rule()->check(Fixtures::comprobante([Fixtures::concepto('10.00')])));
    }

    public function testRechazaCodigosPostalesMalFormados(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('10.00')],
            new Receptor('EKU9003173C9', 'ESCUELA KEMPER URGATE', '1000', '601', 'G03'),
            null,
            lugarExpedicion: '6200',
        );

        $violaciones = $this->rule()->check($c);

        $this->assertCodigos(['formato-codigo-postal', 'formato-codigo-postal'], $violaciones);
        $this->assertSame(['LugarExpedicion', 'Receptor.DomicilioFiscalReceptor'], array_map(fn ($v) => $v->campo, $violaciones));
    }

    public function testRechazaUnaMonedaFueraDelCatalogo(): void
    {
        $c = Fixtures::comprobante([Fixtures::concepto('10.00')], 'mxn');

        $this->assertCodigos(['moneda-desconocida'], $this->rule()->check($c));
    }

    public function testLosDecimalesDeLaMonedaDebenCaberEnLaConfiguracion(): void
    {
        $c = Fixtures::comprobante([Fixtures::concepto('10.00')], 'MXN');   // 2 decimales en c_Moneda

        $this->assertCodigos(['decimales-moneda'], $this->rule(new DecimalConfiguration(12, 1, 1))->check($c));
    }

    public function testRevisaElFormatoDeTipoCambioYConfirmacion(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('10.00')], Fixtures::receptorNominativo(), null, 'USD',
            tipoCambio: '17.1234567', confirmacion: 'ABC'
        );

        $this->assertCodigos(['formato-tipo-cambio', 'formato-confirmacion'], $this->rule()->check($c));
    }

    public function testRechazaUnTipoCambioCero(): void
    {
        $c = Fixtures::comprobanteCon([Fixtures::concepto('10.00')], Fixtures::receptorNominativo(), null, 'USD', tipoCambio: '0.00');

        $this->assertCodigos(['formato-tipo-cambio'], $this->rule()->check($c));
    }

    public function testRevisaLosUuidsRelacionados(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('10.00')], Fixtures::receptorNominativo(), null,
            cfdiRelacionados: [
                new CfdiRelacionados('01', []),
                new CfdiRelacionados('04', ['5FB2822E-396D-4725-8521-CDC4BDD20CCF', 'no-es-uuid']),
            ]
        );

        $violaciones = $this->rule()->check($c);

        $this->assertCodigos(['cfdi-relacionados-vacio', 'formato-uuid'], $violaciones);
        $this->assertSame(
            ['CfdiRelacionados[1]', 'CfdiRelacionados[2].CfdiRelacionado[2].UUID'],
            array_map(fn ($v) => $v->campo, $violaciones)
        );
    }

    public function testRechazaMesesYAnioMalFormados(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('10.00')], Fixtures::receptorPublicoEnGeneral(), new InformacionGlobal('04', '1', 26)
        );

        $this->assertCodigos(['formato-meses', 'formato-anio'], $this->rule()->check($c));
    }

    private function rule(?DecimalConfiguration $config = null): RuleFormatoComprobante
    {
        return new RuleFormatoComprobante($config ?? DecimalConfiguration::sat(), Fixtures::catalogs()->monedas);
    }
}
