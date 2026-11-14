<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Application\AddendaForm;

use PHPUnit\Framework\TestCase;
use SplCfdi\Application\AddendaForm\{AddendaDefaults, AddendaOverrides, AddendaValues, Cell, DefaultValue};
use SplCfdi\Domain\Addendas\DatosAddenda;
use SplCfdi\Tests\Support\AddendaDePrueba;

/** AddendaDePrueba: "codigo" (Cliente, required) and "orden" (Concepto, required). */
final class AddendaValuesTest extends TestCase
{
    private function values(AddendaDefaults $defaults, AddendaOverrides $overrides, int $lines = 2): AddendaValues
    {
        return new AddendaValues(new AddendaDePrueba(), $defaults, $overrides, $lines);
    }

    public function testTheDefaultAppliesWhenNothingWasTyped(): void
    {
        $values = $this->values(new AddendaDefaults(['codigo' => new DefaultValue('REF01', 'Configuración')]), new AddendaOverrides());

        $this->assertEquals(new Cell('REF01', 'REF01', 'Configuración', false), $values->header('codigo'));
        $this->assertEquals(new DatosAddenda('PRUEBA', ['codigo' => 'REF01']), $values->datos());
    }

    public function testWhatTheUserTypesWins(): void
    {
        $values = $this->values(
            new AddendaDefaults(['codigo' => new DefaultValue('REF01', 'Cuenta › Referencia')]),
            new AddendaOverrides(['codigo' => 'REF02']),
        );

        $this->assertEquals(new Cell('REF02', 'REF01', 'Cuenta › Referencia', true), $values->header('codigo'));
        $this->assertSame('REF02', $values->datos()->valor('codigo'));
    }

    /** An empty override clears the default: the addenda goes without it (or the field is reported as missing). */
    public function testAnEmptyOverrideClearsTheDefault(): void
    {
        $values = $this->values(
            new AddendaDefaults(['codigo' => new DefaultValue('REF01', 'Configuración')]),
            new AddendaOverrides(['codigo' => '']),
        );

        $this->assertEquals(new Cell(null, 'REF01', 'Configuración', true), $values->header('codigo'));
        $this->assertEquals(new DatosAddenda('PRUEBA'), $values->datos());
    }

    /** Typing the default again is not an override: if the default changes later, the invoice follows it. */
    public function testTypingTheDefaultIsNotAnOverride(): void
    {
        $values = $this->values(
            new AddendaDefaults(['codigo' => new DefaultValue('REF01', 'Configuración')]),
            new AddendaOverrides(['codigo' => 'REF01']),
        );

        $this->assertFalse($values->header('codigo')->overridden);
    }

    public function testAFieldWithNoDefaultAndNothingTypedIsEmpty(): void
    {
        $this->assertEquals(new Cell(null, null, null, false), $this->values(new AddendaDefaults(), new AddendaOverrides())->header('codigo'));
    }

    /** A line default given once for the invoice (e.g. one purchase order) applies to every line. */
    public function testLineValuesFallBackToTheInvoiceDefault(): void
    {
        $values = $this->values(
            new AddendaDefaults(
                ['orden' => new DefaultValue('A', 'Factura › Orden de compra')],
                [1 => ['orden' => new DefaultValue('B', 'Producto › Código')]],
            ),
            new AddendaOverrides(lines: [0 => ['orden' => 'C']]),
            lines: 3,
        );

        $this->assertEquals(new Cell('C', 'A', 'Factura › Orden de compra', true), $values->line(0, 'orden'));
        $this->assertEquals(new Cell('B', 'B', 'Producto › Código', false), $values->line(1, 'orden'));
        $this->assertEquals(new Cell('A', 'A', 'Factura › Orden de compra', false), $values->line(2, 'orden'));
        $this->assertEquals(new DatosAddenda('PRUEBA', [], [0 => ['orden' => 'C'], 1 => ['orden' => 'B'], 2 => ['orden' => 'A']]), $values->datos());
    }

    /** Overrides for lines the invoice no longer has, or for names the addenda doesn't define, are not used. */
    public function testIgnoresOverridesItCannotPlace(): void
    {
        $values = $this->values(new AddendaDefaults(), new AddendaOverrides(['otro' => 'x', 'orden' => 'y'], [5 => ['orden' => 'z']]), lines: 1);

        $this->assertEquals(new DatosAddenda('PRUEBA'), $values->datos());
    }
}
