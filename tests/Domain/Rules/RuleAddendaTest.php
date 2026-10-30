<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Addendas\{AddendaRegistry, DatosAddenda};
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\{RuleAddenda, Violation};
use SplCfdi\Tests\Support\{AddendaDePrueba, AssertsRules, Fixtures};

final class RuleAddendaTest extends TestCase
{
    use AssertsRules;

    private const VALIDOS = ['codigo' => '123', 'orden' => 'OC-1'];

    /** @return Violation[] */
    private function check(?DatosAddenda $datos, int $conceptos = 1): array
    {
        $c = Fixtures::comprobanteNominativo(array_fill(0, $conceptos, Fixtures::concepto('100.00')));
        $c = new Comprobante(
            $c->version, $c->fecha, $c->moneda, $c->tipoDeComprobante, $c->exportacion, $c->lugarExpedicion,
            $c->emisor, $c->receptor, $c->conceptos, addenda: $datos,
        );

        return (new RuleAddenda(new AddendaRegistry(new AddendaDePrueba())))->check(Fixtures::calculado($c));
    }

    public function testSinAddendaNoRevisaNada(): void
    {
        $this->assertCodigos([], $this->check(null));
    }

    public function testAceptaDatosCompletos(): void
    {
        $this->assertCodigos([], $this->check(new DatosAddenda('PRUEBA', self::VALIDOS)));
    }

    public function testRechazaUnaClaveQueNoExiste(): void
    {
        $violaciones = $this->check(new DatosAddenda('NOEXISTE'));

        $this->assertCodigos(['addenda-desconocida'], $violaciones);
        $this->assertStringContainsString('PRUEBA', $violaciones[0]->message);
    }

    public function testReportaLosRequeridosConLaRutaDelCampo(): void
    {
        $violaciones = $this->check(new DatosAddenda('PRUEBA', ['codigo' => '  ']), 2);

        $this->assertCodigos(['addenda-requerido', 'addenda-requerido', 'addenda-requerido'], $violaciones);
        $this->assertSame(
            ['Addenda.codigo', 'Addenda.Concepto[1].orden', 'Addenda.Concepto[2].orden'],
            array_map(fn (Violation $v) => $v->campo, $violaciones),
        );
        $this->assertStringContainsString('Concepto 2', $violaciones[2]->message);
    }

    public function testUnCampoDeConceptoDadoPorConceptoSustituyeAlGeneral(): void
    {
        $datos = new DatosAddenda('PRUEBA', ['codigo' => '123'], [1 => ['orden' => 'OC-2']]);

        $this->assertSame(['Addenda.Concepto[1].orden'], array_map(fn (Violation $v) => $v->campo, $this->check($datos, 2)));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidos(): iterable
    {
        yield 'patrón' => ['codigo', '12a', 'addenda-patron'];
        yield 'opción' => ['tipo', 'C', 'addenda-tipo'];
        yield 'entero' => ['cantidad', '1.5', 'addenda-tipo'];
        yield 'decimal' => ['monto', '1,5', 'addenda-tipo'];
        yield 'máximo' => ['nombre', 'ñandúes', 'addenda-longitud'];
        yield 'mínimo' => ['nombre', 'a', 'addenda-longitud'];
    }

    #[DataProvider('invalidos')]
    public function testRevisaLaFormaDeCadaValor(string $campo, string $valor, string $codigo): void
    {
        $violaciones = $this->check(new DatosAddenda('PRUEBA', [...self::VALIDOS, $campo => $valor]));

        $this->assertCodigos([$codigo], $violaciones);
        $this->assertSame("Addenda.$campo", $violaciones[0]->campo);
    }

    public function testCuentaCaracteresNoBytes(): void
    {
        $this->assertCodigos([], $this->check(new DatosAddenda('PRUEBA', [...self::VALIDOS, 'nombre' => 'ñandú'])));
    }

    public function testElMensajeDeOpcionListaLasPermitidas(): void
    {
        $violaciones = $this->check(new DatosAddenda('PRUEBA', [...self::VALIDOS, 'tipo' => 'C']));

        $this->assertStringContainsString('A (Alfa), B (Beta)', $violaciones[0]->message);
    }

    public function testReportaCamposQueLaAddendaNoDefine(): void
    {
        $datos = new DatosAddenda('PRUEBA', [...self::VALIDOS, 'otro' => 'x'], [0 => ['codigo' => '123']]);

        $violaciones = $this->check($datos);

        $this->assertCodigos(['addenda-campo-desconocido', 'addenda-campo-desconocido'], $violaciones);
        $this->assertSame(['Addenda.otro', 'Addenda.Concepto[1].codigo'], array_map(fn (Violation $v) => $v->campo, $violaciones));
    }

    public function testReportaDatosDeConceptosQueNoExisten(): void
    {
        $violaciones = $this->check(new DatosAddenda('PRUEBA', self::VALIDOS, [3 => ['orden' => 'x']]));

        $this->assertCodigos(['addenda-concepto-inexistente'], $violaciones);
        $this->assertSame('Addenda.Concepto[4]', $violaciones[0]->campo);
    }
}
