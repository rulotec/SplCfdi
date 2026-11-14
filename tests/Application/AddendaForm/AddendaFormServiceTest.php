<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Application\AddendaForm;

use PHPUnit\Framework\TestCase;
use SplCfdi\Application\AddendaForm\{AddendaDefaults, AddendaForm, AddendaOverrides, DefaultValue};
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Models\{Comprobante, Concepto};
use SplCfdi\Domain\Addendas\AddendaRegistry;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Tests\Support\{AddendaDePrueba, EjemplosAddenda, Fixtures};

/** The Volkswagen PSV example: the host maps part of the data and the user types the rest in the form. */
final class AddendaFormServiceTest extends TestCase
{
    /** @param array<string, string> $sin the example's invoice values to leave out of the defaults */
    private function defaults(array $sin = []): AddendaDefaults
    {
        $valores = array_diff_key((array) EjemplosAddenda::volkswagen()->addenda?->valores, $sin);

        return new AddendaDefaults(array_map(fn (string $v) => new DefaultValue($v, 'Configuración'), $valores));
    }

    private function overrides(): AddendaOverrides
    {
        return new AddendaOverrides(lines: [0 => ['posicion' => '1', 'nota' => 'SERVICIO DE PRUEBA']]);
    }

    /** @param array<int, string> $lineNotices */
    private function build(
        ?Comprobante $comprobante = null,
        ?AddendaDefaults $defaults = null,
        ?AddendaOverrides $overrides = null,
        string $clave = 'PSV',
        array $lineNotices = [],
    ): AddendaForm {
        return CfdiFactory::createAddendaFormService()->build(
            $comprobante ?? EjemplosAddenda::volkswagen(), $clave, $defaults ?? $this->defaults(), $overrides ?? $this->overrides(), $lineNotices,
        );
    }

    /** @return list<array{code: string, path: ?string, field: ?string, line: ?int}> */
    private function violations(AddendaForm $form): array
    {
        return array_map(
            fn (array $v) => ['code' => $v['code'], 'path' => $v['path'], 'field' => $v['field'], 'line' => $v['line']],
            $form->toArray()['violations'],
        );
    }

    public function testDefaultsPlusWhatTheUserTypedMakeAValidAddenda(): void
    {
        $form = $this->build();

        $this->assertSame([], $this->violations($form));
        $this->assertTrue($form->toArray()['valid']);
        $this->assertSame('1', $form->datos()->valorConcepto(0, 'posicion'));
    }

    public function testAMissingValueIsReportedOnItsCell(): void
    {
        $form = $this->build(defaults: $this->defaults(['codigoProveedor' => '']), overrides: new AddendaOverrides());

        $this->assertSame([
            ['code' => 'addenda-requerido', 'path' => 'Addenda.codigoProveedor', 'field' => 'codigoProveedor', 'line' => null],
            ['code' => 'addenda-requerido', 'path' => 'Addenda.Concepto[1].posicion', 'field' => 'posicion', 'line' => 0],
        ], $this->violations($form));
        $this->assertFalse($form->toArray()['valid']);
    }

    /** While the typed data breaks the addenda's rules, its XSD would only repeat them. */
    public function testTheXsdIsCheckedOnlyOnceTheRulesPass(): void
    {
        $form = $this->build(overrides: new AddendaOverrides(['codigoProveedor' => 'ABC']));

        $this->assertSame(['addenda-patron', 'addenda-requerido'], array_column($this->violations($form), 'code'));
    }

    /** Once the rules pass, the addenda's XSD is checked too; its paths point into the addenda's XML. */
    public function testChecksTheXsdOnceTheRulesPass(): void
    {
        $addendas = AddendaRegistry::standard(new BcMathDecimalMath(DecimalConfiguration::sat()))
            ->con(new AddendaDePrueba(EjemplosAddenda::DIR . '/../addenda-prueba.xsd'));
        $defaults = new AddendaDefaults(['codigo' => new DefaultValue('123'), 'orden' => new DefaultValue('OC1')]);

        $form = CfdiFactory::createAddendaFormService(addendas: $addendas)
            ->build(Fixtures::comprobante([Fixtures::concepto('100.00')]), 'PRUEBA', $defaults, new AddendaOverrides());

        $this->assertSame([['code' => 'xsd-patron', 'path' => 'Addenda.codigo', 'field' => 'codigo', 'line' => null]], $this->violations($form));
    }

    /** Problems of the invoice itself have no cell in the form: the page lists them apart. */
    public function testInvoiceProblemsAreNotPlacedOnACell(): void
    {
        $c = EjemplosAddenda::volkswagen();
        $concepto = $c->conceptos[0];
        $mal = new Concepto($concepto->claveProdServ, 'abc', $concepto->claveUnidad, $concepto->unidad, $concepto->descripcion,
            $concepto->valorUnitario, $concepto->objetoImp, $concepto->impuestosTrasladados);

        $form = $this->build(new Comprobante(
            $c->version, $c->fecha, $c->moneda, $c->tipoDeComprobante, $c->exportacion, $c->lugarExpedicion, $c->emisor,
            $c->receptor, [$mal], folio: $c->folio, tipoCambio: $c->tipoCambio,
        ));

        $this->assertSame([['code' => 'formato-decimal', 'path' => 'Concepto[1].Cantidad', 'field' => null, 'line' => null]], $this->violations($form));
        $this->assertNull($form->toArray()['lines'][0]['amount'], 'without a valid Cantidad there is no amount');
    }

    /** The host flags lines whose typed data may no longer apply (e.g. their product changed). */
    public function testShowsTheHostsNoticeOnALine(): void
    {
        $lines = $this->build(lineNotices: [0 => 'El producto de esta línea cambió; revisa sus datos.'])->toArray()['lines'];

        $this->assertSame('El producto de esta línea cambió; revisa sus datos.', $lines[0]['notice']);
    }

    public function testAnUnknownAddendaHasNoFields(): void
    {
        $datos = $this->build(clave: 'NOEXISTE')->toArray();

        $this->assertSame(['key' => 'NOEXISTE', 'name' => null], $datos['addenda']);
        $this->assertSame([], $datos['fields']);
        $this->assertSame(['addenda-desconocida'], array_column($datos['violations'], 'code'));
    }

    /** The contract the form page consumes (docs/addendas.md describes it). */
    public function testTheContract(): void
    {
        $datos = $this->build(overrides: new AddendaOverrides(['remision' => 'REM02'], [0 => ['posicion' => '1']]))->toArray();

        $this->assertSame(['key' => 'PSV', 'name' => 'Volkswagen de México – Servicios (PSV)'], $datos['addenda']);

        $campos = array_column($datos['fields'], null, 'name');
        $this->assertSame([
            'name' => 'codigoProveedor', 'label' => 'Código de proveedor', 'level' => 'cliente', 'type' => 'texto',
            'required' => true, 'minLength' => null, 'maxLength' => null, 'pattern' => '[0-9]{10}', 'options' => [],
            'help' => 'diez dígitos, asignado por VW', 'fallback' => null,
        ], $campos['codigoProveedor']);
        $this->assertSame([['value' => 'VW', 'label' => 'Volkswagen México'], ['value' => 'INFODE', 'label' => 'INFODE'],
            ['value' => 'VWSP', 'label' => 'Volkswagen Servicios Profesionales']], $campos['division']['options']);

        // Header cells hold the invoice-level fields only; line fields are in each line.
        $this->assertArrayNotHasKey('posicion', $datos['header']);
        $this->assertSame(['value' => 'REM02', 'default' => 'REM01', 'source' => 'Configuración', 'overridden' => true], $datos['header']['remision']);
        $this->assertSame(['value' => null, 'default' => null, 'source' => null, 'overridden' => false], $datos['header']['cancelaSustituye']);

        $this->assertCount(1, $datos['lines']);
        $linea = $datos['lines'][0];
        $this->assertSame(
            [
                'index' => 0, 'number' => 1, 'productKey' => '86101705', 'description' => 'Servicio de prueba año 1',
                'quantity' => '1.000000', 'unit' => 'MON', 'unitPrice' => '5000.000000', 'amount' => '5000.000000', 'notice' => null,
            ],
            array_diff_key($linea, ['values' => true]),
        );
        $this->assertSame(['value' => '1', 'default' => null, 'source' => null, 'overridden' => true], $linea['values']['posicion']);
        $this->assertSame(['value' => '9000000001', 'default' => '9000000001', 'source' => 'Configuración', 'overridden' => false], $linea['values']['ordenCompra']);

        $this->assertNotFalse(json_encode($datos));
    }
}
