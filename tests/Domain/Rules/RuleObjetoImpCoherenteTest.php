<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{Comprobante, Concepto, ImpuestoRetenido, ImpuestoTrasladado, TipoFactor};
use SplCfdi\Domain\Rules\RuleObjetoImpCoherente;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleObjetoImpCoherenteTest extends TestCase
{
    use AssertsRules;

    public function testAceptaLosCasosCoherentes(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::concepto('100.00'),            // 02 con traslado
            Fixtures::conceptoExento('50.00'),       // 02 con Exento
            Fixtures::conceptoSinImpuestos('10.00'), // 01 sin impuestos
        ]);

        $this->assertCodigos([], $this->check($c));
    }

    public function testObjetoImp02ExigeImpuestos(): void
    {
        $c = Fixtures::comprobanteNominativo([Fixtures::conceptoConObjetoImp('100.00', '02')]);

        $this->assertCodigos(['objeto-imp-02-sin-impuestos'], $this->check($c));
    }

    /** @return iterable<string, array{string}> */
    public static function sinNodoImpuestos(): iterable
    {
        yield '01 no objeto' => ['01'];
        yield '03 sin obligación de desglose' => ['03'];
        yield '04 no causa impuesto' => ['04'];
        yield '05 IVA crédito PODEBI' => ['05'];
    }

    #[DataProvider('sinNodoImpuestos')]
    public function testEstosObjetoImpNoAdmitenImpuestos(string $objetoImp): void
    {
        $sinImpuestos = Fixtures::comprobanteNominativo([Fixtures::conceptoConObjetoImp('100.00', $objetoImp)]);
        $conImpuestos = Fixtures::comprobanteNominativo([
            Fixtures::conceptoConObjetoImp('100.00', $objetoImp, [self::iva()]),
        ]);

        $this->assertCodigos([], $this->check($sinImpuestos));
        $this->assertCodigos(['objeto-imp-sin-impuestos'], $this->check($conImpuestos));
    }

    public function testUnExentoFueraDe02SoloSeReportaUnaVez(): void
    {
        $c = Fixtures::comprobanteNominativo([
            Fixtures::conceptoConObjetoImp('100.00', '03', [new ImpuestoTrasladado('002', TipoFactor::Exento)]),
        ]);

        $this->assertCodigos(['objeto-imp-sin-impuestos'], $this->check($c));
    }

    /** @return iterable<string, array{string}> */
    public static function sinIvaNiIeps(): iterable
    {
        yield '06 IVA no traslado' => ['06'];
        yield '08 sin desglose de IEPS' => ['08'];
    }

    #[DataProvider('sinIvaNiIeps')]
    public function testSinIvaNiIepsPeroConRetencionDeIsr(string $objetoImp): void
    {
        $conIsr = Fixtures::comprobanteNominativo([self::concepto($objetoImp, [], [self::isr()])]);
        $sinNada = Fixtures::comprobanteNominativo([self::concepto($objetoImp, [], [])]);
        $conIva = Fixtures::comprobanteNominativo([self::concepto($objetoImp, [self::iva()], [])]);
        $conRetencionIeps = Fixtures::comprobanteNominativo([
            self::concepto($objetoImp, [], [new ImpuestoRetenido('003', TipoFactor::Tasa, '0.265000')]),
        ]);

        $this->assertCodigos([], $this->check($conIsr));
        $this->assertCodigos([], $this->check($sinNada));
        $this->assertCodigos(['objeto-imp-sin-iva-ieps'], $this->check($conIva));
        $this->assertCodigos(['objeto-imp-sin-iva-ieps'], $this->check($conRetencionIeps));
    }

    public function testObjetoImp07ExigeIepsYNoAdmiteIva(): void
    {
        $ieps = new ImpuestoTrasladado('003', TipoFactor::Tasa, '0.080000');

        $this->assertCodigos([], $this->check(Fixtures::comprobanteNominativo([self::concepto('07', [$ieps], [self::isr()])])));
        $this->assertCodigos(['objeto-imp-07-sin-ieps'], $this->check(Fixtures::comprobanteNominativo([self::concepto('07', [], [])])));
        $this->assertCodigos(
            ['objeto-imp-sin-iva'],
            $this->check(Fixtures::comprobanteNominativo([self::concepto('07', [$ieps, self::iva()], [])]))
        );
    }

    private static function iva(): ImpuestoTrasladado
    {
        return new ImpuestoTrasladado('002', TipoFactor::Tasa, '0.160000');
    }

    private static function isr(): ImpuestoRetenido
    {
        return new ImpuestoRetenido('001', TipoFactor::Tasa, '0.100000');
    }

    /**
     * @param ImpuestoTrasladado[] $traslados
     * @param ImpuestoRetenido[]   $retenciones
     */
    private static function concepto(string $objetoImp, array $traslados, array $retenciones): Concepto
    {
        return new Concepto(
            claveProdServ: '81112100', cantidad: '1', claveUnidad: 'E48', unidad: 'Servicio', descripcion: 'Prueba',
            valorUnitario: '100.00', objetoImp: $objetoImp, impuestosTrasladados: $traslados, impuestosRetenidos: $retenciones,
        );
    }

    /** @return Violation[] */
    private function check(Comprobante $comprobante): array
    {
        return (new RuleObjetoImpCoherente())->check(Fixtures::calculado($comprobante));
    }
}
