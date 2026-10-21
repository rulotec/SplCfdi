<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Contracts\TipoCambioFix;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\RuleTipoCambio;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleTipoCambioTest extends TestCase
{
    use AssertsRules;

    /** @return iterable<string, array{string, ?string, string[]}> */
    public static function casos(): iterable
    {
        yield 'MXN sin TipoCambio' => ['MXN', null, []];
        yield 'MXN con 1' => ['MXN', '1.000000', []];
        yield 'MXN con otro' => ['MXN', '17.50', ['tipo-cambio-mxn']];
        yield 'XXX sin TipoCambio' => ['XXX', null, []];
        yield 'XXX con TipoCambio' => ['XXX', '1', ['tipo-cambio-xxx']];
        yield 'USD sin TipoCambio' => ['USD', null, ['tipo-cambio-requerido']];
        yield 'USD con TipoCambio' => ['USD', '17.512300', []];
    }

    /** @param string[] $esperados */
    #[DataProvider('casos')]
    public function testTipoCambioSegunLaMoneda(string $moneda, ?string $tipoCambio, array $esperados): void
    {
        $this->assertCodigos($esperados, $this->check($this->comprobante($moneda, $tipoCambio), null));
    }

    public function testFueraDelRangoDelFixExigeConfirmacion(): void
    {
        // USD: variación de 500 % en c_Moneda; con FIX 17 el rango es 0 a 102
        $violaciones = $this->check($this->comprobante('USD', '150.00'), $this->fix('17.000000'));

        $this->assertCodigos(['tipo-cambio-fuera-de-rango'], $violaciones);
        $this->assertStringContainsString('Confirmacion', $violaciones[0]->message);
    }

    public function testDentroDelRangoOConConfirmacionEsValido(): void
    {
        $this->assertCodigos([], $this->check($this->comprobante('USD', '100.00'), $this->fix('17.000000')));
        $this->assertCodigos([], $this->check($this->comprobante('USD', '150.00', 'Ab123'), $this->fix('17.000000')));
    }

    public function testSinFixNoSeRevisaElRango(): void
    {
        $this->assertCodigos([], $this->check($this->comprobante('USD', '150.00'), $this->fix(null)));
    }

    private function comprobante(string $moneda, ?string $tipoCambio, ?string $confirmacion = null): Comprobante
    {
        return Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')], Fixtures::receptorNominativo(), null, $moneda,
            tipoCambio: $tipoCambio, confirmacion: $confirmacion
        );
    }

    /** @param numeric-string|null $valor */
    private function fix(?string $valor): TipoCambioFix
    {
        return new class ($valor) implements TipoCambioFix {
            /** @param numeric-string|null $valor */
            public function __construct(private readonly ?string $valor) {}

            public function fix(string $moneda, DateTimeImmutable $fecha): ?string
            {
                return $this->valor;
            }
        };
    }

    /** @return Violation[] */
    private function check(Comprobante $c, ?TipoCambioFix $fix): array
    {
        $rule = new RuleTipoCambio(Fixtures::catalogs()->monedas, new BcMathDecimalMath(DecimalConfiguration::sat()), $fix);

        return $rule->check(Fixtures::calculado($c));
    }
}
