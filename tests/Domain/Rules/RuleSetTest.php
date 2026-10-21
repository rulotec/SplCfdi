<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\Receptor;
use SplCfdi\Domain\Rules\RuleSet;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleSetTest extends TestCase
{
    use AssertsRules;

    public function testAceptaUnComprobanteValido(): void
    {
        $c = Fixtures::calculado(Fixtures::comprobante([Fixtures::concepto('100.00')]));

        $this->assertCodigos([], RuleSet::standard(Fixtures::clock(), Fixtures::catalogs())->check($c));
    }

    public function testReuneTodasLasViolacionesYNoSoloLaPrimera(): void
    {
        $receptor = new Receptor('XAXX010101000', 'PUBLICO EN GENERAL', '42501', '616', 'S01');
        $c = Fixtures::calculado(Fixtures::comprobanteCon([Fixtures::concepto('100.00')], $receptor, null));

        $this->assertCodigos(
            ['informacion-global-requerida', 'domicilio-receptor-generico'],
            RuleSet::standard(Fixtures::clock(), Fixtures::catalogs())->check($c)
        );
    }
}
