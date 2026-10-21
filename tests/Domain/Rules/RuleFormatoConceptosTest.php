<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{Concepto, ImpuestoRetenido, ImpuestoTrasladado, TipoFactor};
use SplCfdi\Domain\Rules\RuleFormatoConceptos;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleFormatoConceptosTest extends TestCase
{
    use AssertsRules;

    public function testAceptaConceptosBienFormados(): void
    {
        $this->assertCodigos([], $this->check(
            Fixtures::concepto('10.00', '0.160000', '1.5', '1.00', [new ImpuestoRetenido('002', TipoFactor::Tasa, '0.106667')]),
            Fixtures::conceptoExento('10.00'),
            new Concepto('01010101', '1', 'E48', 'Servicio', 'Cero a la izquierda', '10.00', '01', []),
        ));
    }

    #[DataProvider('decimalesInvalidos')]
    public function testRechazaCantidadValorUnitarioYDescuentoMalFormados(Concepto $concepto, string $campo): void
    {
        $violaciones = $this->check($concepto);

        $this->assertCodigos(['formato-decimal'], $violaciones);
        $this->assertSame($campo, $violaciones[0]->campo);
    }

    public static function decimalesInvalidos(): array
    {
        return [
            'cantidad con 7 decimales'       => [Fixtures::concepto('10.00', '0.160000', '1.1234567'), 'Concepto[1].Cantidad'],
            'cantidad no numérica'           => [Fixtures::concepto('10.00', '0.160000', 'abc'), 'Concepto[1].Cantidad'],
            'cantidad negativa'              => [Fixtures::concepto('10.00', '0.160000', '-1'), 'Concepto[1].Cantidad'],
            'valor unitario con 7 decimales' => [Fixtures::concepto('10.1234567'), 'Concepto[1].ValorUnitario'],
            'valor unitario con comas'       => [Fixtures::concepto('1,000.00'), 'Concepto[1].ValorUnitario'],
            'valor unitario vacío'           => [Fixtures::concepto(''), 'Concepto[1].ValorUnitario'],
            'descuento negativo'             => [Fixtures::concepto('10.00', '0.160000', '1', '-1'), 'Concepto[1].Descuento'],
            'tasa con 7 decimales'           => [Fixtures::concepto('10.00', '0.1600001'), 'Concepto[1].Traslado[1].TasaOCuota'],
        ];
    }

    public function testRechazaClaveProdServSinCerosALaIzquierda(): void
    {
        $violaciones = $this->check(new Concepto('1010101', '1', 'E48', 'Servicio', 'Prueba', '10.00', '01', []));

        $this->assertCodigos(['formato-clave-prod-serv'], $violaciones);
        $this->assertStringContainsString('ceros a la izquierda', $violaciones[0]->message);
    }

    public function testRevisaLaCoherenciaDeTipoFactorYTasa(): void
    {
        $this->assertCodigos(['exento-con-tasa', 'tasa-requerida', 'retencion-exenta'], $this->check(
            Fixtures::conceptoConObjetoImp('10.00', '02', [new ImpuestoTrasladado('002', TipoFactor::Exento, '0.160000')]),
            Fixtures::conceptoConObjetoImp('10.00', '02', [new ImpuestoTrasladado('002', TipoFactor::Tasa)]),
            Fixtures::concepto('10.00', '0.160000', '1', null, [new ImpuestoRetenido('002', TipoFactor::Exento, '0.106667')]),
        ));
    }

    public function testLaBaseDelHostSoloVaConCuota(): void
    {
        $violaciones = $this->check(
            Fixtures::conceptoConObjetoImp('10.00', '02', [new ImpuestoTrasladado('003', TipoFactor::Cuota, '6.453000')]),
            Fixtures::conceptoConObjetoImp('10.00', '02', [new ImpuestoTrasladado('002', TipoFactor::Tasa, '0.160000', '5')]),
            Fixtures::conceptoConObjetoImp('10.00', '02', [new ImpuestoTrasladado('003', TipoFactor::Cuota, '6.453000', '1,5')]),
            Fixtures::conceptoConObjetoImp('10.00', '02', [new ImpuestoTrasladado('003', TipoFactor::Cuota, '6.453000', '40')]),
        );

        $this->assertCodigos(['base-cuota-requerida', 'base-solo-con-cuota', 'formato-decimal'], $violaciones);
        $this->assertSame(
            ['Concepto[1].Traslado[1].Base', 'Concepto[2].Traslado[1].Base', 'Concepto[3].Traslado[1].Base'],
            array_map(fn (Violation $v) => $v->campo, $violaciones)
        );
    }

    public function testReportaTodosLosErroresDeTodosLosConceptosConSuUbicacion(): void
    {
        $violaciones = $this->check(
            Fixtures::concepto('abc', '0.160000', 'xyz'),
            Fixtures::concepto('10.00'),
            Fixtures::concepto('10.00', '0.160000', '1', 'n/a'),
        );

        $this->assertSame(
            ['Concepto[1].Cantidad', 'Concepto[1].ValorUnitario', 'Concepto[3].Descuento'],
            array_map(fn ($v) => $v->campo, $violaciones)
        );
        $this->assertSame(
            'Concepto 3 ("Prueba"): Descuento "n/a" no es válido: debe ser un número no negativo con máximo 6 decimales, sin comas ni símbolos (p. ej. "1500.50").',
            $violaciones[2]->message
        );
    }

    /** @return Violation[] */
    private function check(Concepto ...$conceptos): array
    {
        return (new RuleFormatoConceptos())->check(Fixtures::comprobanteNominativo($conceptos));
    }
}
