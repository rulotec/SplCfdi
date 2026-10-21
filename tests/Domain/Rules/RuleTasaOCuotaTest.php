<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{Comprobante, Concepto, ImpuestoRetenido, ImpuestoTrasladado, TipoFactor};
use SplCfdi\Domain\Rules\RuleTasaOCuota;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleTasaOCuotaTest extends TestCase
{
    use AssertsRules;

    /** @return iterable<string, array{ImpuestoTrasladado[], ImpuestoRetenido[], string[]}> */
    public static function casos(): iterable
    {
        $iva = fn (string $tasa) => new ImpuestoTrasladado('002', TipoFactor::Tasa, $tasa);

        yield 'IVA 16 %' => [[$iva('0.160000')], [], []];
        yield 'IVA 8 % (frontera)' => [[$iva('0.08')], [], []];
        yield 'IVA 0 %' => [[$iva('0.000000')], [], []];
        yield 'IVA 15 %' => [[$iva('0.150000')], [], ['tasa-o-cuota-invalida']];
        yield 'IVA exento' => [[new ImpuestoTrasladado('002', TipoFactor::Exento)], [], []];
        yield 'retención de IVA dentro del rango' => [[$iva('0.16')], [new ImpuestoRetenido('002', TipoFactor::Tasa, '0.106667')], []];
        yield 'retención de IVA fuera del rango' => [[$iva('0.16')], [new ImpuestoRetenido('002', TipoFactor::Tasa, '0.200000')], ['tasa-o-cuota-invalida']];
        yield 'retención de ISR' => [[$iva('0.16')], [new ImpuestoRetenido('001', TipoFactor::Tasa, '0.100000')], []];
        yield 'retención de ISR con Cuota' => [[$iva('0.16')], [new ImpuestoRetenido('001', TipoFactor::Cuota, '0.100000', '1')], ['tasa-o-cuota-invalida']];
        yield 'traslado de ISR' => [[new ImpuestoTrasladado('001', TipoFactor::Tasa, '0.100000')], [], ['tasa-o-cuota-invalida']];
        yield 'IEPS por Cuota' => [[new ImpuestoTrasladado('003', TipoFactor::Cuota, '6.453000', '10')], [], []];
        yield 'IEPS por Cuota excesiva' => [[new ImpuestoTrasladado('003', TipoFactor::Cuota, '80.000000', '10')], [], ['tasa-o-cuota-invalida']];
        yield 'IEPS 26.5 %' => [[new ImpuestoTrasladado('003', TipoFactor::Tasa, '0.265000')], [], []];
        yield 'IEPS 10 %' => [[new ImpuestoTrasladado('003', TipoFactor::Tasa, '0.100000')], [], ['tasa-o-cuota-invalida']];
    }

    /**
     * @param ImpuestoTrasladado[] $traslados
     * @param ImpuestoRetenido[]   $retenciones
     * @param string[]             $esperados
     */
    #[DataProvider('casos')]
    public function testTasaOCuotaContraElCatalogo(array $traslados, array $retenciones, array $esperados): void
    {
        $this->assertCodigos($esperados, $this->check($this->comprobante($traslados, $retenciones)));
    }

    public function testElMensajeExplicaLosValoresPermitidos(): void
    {
        $violaciones = $this->check($this->comprobante([new ImpuestoTrasladado('002', TipoFactor::Tasa, '0.150000')], []));

        $this->assertSame('Concepto[1].Traslado[1].TasaOCuota', $violaciones[0]->campo);
        $this->assertStringContainsString('0.160000', $violaciones[0]->message);
    }

    /**
     * @param ImpuestoTrasladado[] $traslados
     * @param ImpuestoRetenido[]   $retenciones
     */
    private function comprobante(array $traslados, array $retenciones): Comprobante
    {
        return Fixtures::comprobanteNominativo([new Concepto(
            claveProdServ: '81112100', cantidad: '1', claveUnidad: 'E48', unidad: 'Servicio', descripcion: 'Prueba',
            valorUnitario: '100.00', objetoImp: '02', impuestosTrasladados: $traslados, impuestosRetenidos: $retenciones,
        )]);
    }

    /** @return Violation[] */
    private function check(Comprobante $c): array
    {
        $rule = new RuleTasaOCuota(Fixtures::catalogs()->tasasOCuotas, new BcMathDecimalMath(DecimalConfiguration::sat()));

        return $rule->check(Fixtures::calculado($c));
    }
}
