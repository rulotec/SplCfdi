<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{ImpuestoRetenido, TipoFactor};
use SplCfdi\Domain\Rules\RuleTotalNoNegativo;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleTotalNoNegativoTest extends TestCase
{
    use AssertsRules;

    public function testRechazaUnTotalNegativoPorRetenciones(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::concepto('100.00', '0.000000', '1', null, [new ImpuestoRetenido('001', TipoFactor::Tasa, '1.500000')]),
        ]);

        $this->assertCodigos(['total-negativo'], (new RuleTotalNoNegativo())->check(Fixtures::calculado($c)));
    }

    public function testAceptaRetencionesNormales(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::concepto('100.00', '0.160000', '1', null, [new ImpuestoRetenido('001', TipoFactor::Tasa, '0.100000')]),
        ]);

        $this->assertCodigos([], (new RuleTotalNoNegativo())->check(Fixtures::calculado($c)));
    }

    public function testUnTotalCeroNoEsNegativo(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::concepto('100.00', '0.000000', '1', null, [new ImpuestoRetenido('001', TipoFactor::Tasa, '1.000000')]),
        ]);

        $this->assertCodigos([], (new RuleTotalNoNegativo())->check(Fixtures::calculado($c)));
    }
}
