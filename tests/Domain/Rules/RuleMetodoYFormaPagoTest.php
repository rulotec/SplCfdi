<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Rules\RuleMetodoYFormaPago;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleMetodoYFormaPagoTest extends TestCase
{
    use AssertsRules;

    /** @return iterable<string, array{string, ?string, ?string, string[]}> */
    public static function casos(): iterable
    {
        yield 'I con PUE y efectivo' => ['I', 'PUE', '01', []];
        yield 'I con PPD y 99' => ['I', 'PPD', '99', []];
        yield 'I con PPD sin 99' => ['I', 'PPD', '03', ['ppd-requiere-forma-pago-99']];
        yield 'I con PUE y 99' => ['I', 'PUE', '99', ['pue-con-forma-pago-99']];
        yield 'I sin ninguno' => ['I', null, null, []];
        yield 'P sin ninguno' => ['P', null, null, []];
        yield 'P con ambos' => ['P', 'PUE', '01', ['forma-pago-no-aplica', 'metodo-pago-no-aplica']];
        yield 'T con FormaPago' => ['T', null, '01', ['forma-pago-no-aplica']];
        // Nómina: sin FormaPago, pero el SAT sí admite MetodoPago (CFDI40125 solo excluye T y P)
        yield 'N con PUE' => ['N', 'PUE', null, []];
        yield 'N con FormaPago' => ['N', 'PUE', '99', ['forma-pago-no-aplica']];
    }

    /** @param string[] $esperados */
    #[DataProvider('casos')]
    public function testMetodoYFormaDePago(string $tipo, ?string $metodo, ?string $forma, array $esperados): void
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorNominativo(), null,
            tipoDeComprobante: $tipo, metodoPago: $metodo, formaPago: $forma
        );

        $this->assertCodigos($esperados, (new RuleMetodoYFormaPago())->check(Fixtures::calculado($c)));
    }
}
