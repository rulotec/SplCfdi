<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{InformacionGlobal, Moneda, Receptor};
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

    public function testRechazaMonedaMalFormada(): void
    {
        $c = Fixtures::comprobante([Fixtures::concepto('10.00')], new Moneda('mxn', 7));

        $this->assertCodigos(['formato-moneda', 'decimales-moneda'], $this->rule()->check($c));
    }

    public function testRechazaMesesYAnioMalFormados(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('10.00')], Fixtures::receptorPublicoEnGeneral(), new InformacionGlobal('04', '1', 26)
        );

        $this->assertCodigos(['formato-meses', 'formato-anio'], $this->rule()->check($c));
    }

    private function rule(): RuleFormatoComprobante
    {
        return new RuleFormatoComprobante(DecimalConfiguration::sat());
    }
}
