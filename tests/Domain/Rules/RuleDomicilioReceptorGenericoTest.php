<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\Receptor;
use SplCfdi\Domain\Rules\RuleDomicilioReceptorGenerico;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleDomicilioReceptorGenericoTest extends TestCase
{
    use AssertsRules;

    #[DataProvider('rfcGenericos')]
    public function testExigeQueCoincidaConElLugarDeExpedicion(string $rfc): void
    {
        $receptor = new Receptor($rfc, 'PUBLICO EN GENERAL', '42501', '616', 'S01');   // el lugar de expedición es 62000
        $c = Fixtures::comprobanteCon([Fixtures::concepto('100.00')], $receptor, null);

        $this->assertCodigos(['domicilio-receptor-generico'], (new RuleDomicilioReceptorGenerico())->check(Fixtures::calculado($c)));
    }

    public static function rfcGenericos(): array
    {
        return ['nacional' => ['XAXX010101000'], 'extranjero' => ['XEXX010101000']];
    }

    public function testAceptaElMismoCodigoPostal(): void
    {
        $c = Fixtures::comprobante([Fixtures::concepto('100.00')]);

        $this->assertCodigos([], (new RuleDomicilioReceptorGenerico())->check(Fixtures::calculado($c)));
    }

    public function testNoAplicaAUnReceptorIdentificado(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::concepto('100.00')]);   // 42501 vs 62000

        $this->assertCodigos([], (new RuleDomicilioReceptorGenerico())->check(Fixtures::calculado($c)));
    }
}
