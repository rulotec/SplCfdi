<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Rules\RuleInformacionGlobal;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleInformacionGlobalTest extends TestCase
{
    use AssertsRules;

    private RuleInformacionGlobal $regla;

    protected function setUp(): void
    {
        $this->regla = new RuleInformacionGlobal();
    }

    public function testExigeInformacionGlobalParaElPublicoEnGeneral(): void
    {
        $c = Fixtures::comprobanteCon([Fixtures::concepto('100.00')], Fixtures::receptorPublicoEnGeneral(), null);

        $this->assertCodigos(['informacion-global-requerida'], $this->regla->check(Fixtures::calculado($c)));
    }

    public function testNoExigeInformacionGlobalAUnReceptorIdentificado(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')]);

        $this->assertCodigos([], $this->regla->check(Fixtures::calculado($c)));
    }

    public function testAceptaElCfdiGlobalCompleto(): void
    {
        $c = Fixtures::comprobante([Fixtures::concepto('100.00')]);

        $this->assertCodigos([], $this->regla->check(Fixtures::calculado($c)));
    }

    public function testExigeLosDatosDelReceptorEnUnCfdiGlobal(): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorNominativo(), Fixtures::informacionGlobal()
        );

        // Rfc y Nombre difieren de los del público en general (régimen y uso: RuleRfcGenerico)
        $this->assertCodigos(
            array_fill(0, 2, 'informacion-global-receptor'),
            $this->regla->check(Fixtures::calculado($c))
        );
    }
}
