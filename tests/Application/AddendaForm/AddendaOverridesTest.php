<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Application\AddendaForm;

use PHPUnit\Framework\TestCase;
use SplCfdi\Application\AddendaForm\AddendaOverrides;

final class AddendaOverridesTest extends TestCase
{
    public function testReadsWhatTheFormSends(): void
    {
        $overrides = AddendaOverrides::fromArray([
            'values' => ['remision' => 'REM01', 'nota' => ''],
            'lines' => ['0' => ['posicion' => '1'], 2 => ['ordenCompra' => '9000000001']],
        ]);

        $this->assertSame(['remision' => 'REM01', 'nota' => ''], $overrides->values);
        $this->assertSame([0 => ['posicion' => '1'], 2 => ['ordenCompra' => '9000000001']], $overrides->lines);
    }

    /** It comes from the browser: anything that isn't text under a field name is dropped. */
    public function testDropsWhatIsNotAValue(): void
    {
        $overrides = AddendaOverrides::fromArray([
            'values' => ['a' => 'x', 'b' => 3, 'c' => ['x'], 5 => 'y'],
            'lines' => ['uno' => ['a' => 'x'], -1 => ['a' => 'x'], 1 => 'x', 2 => ['a' => null, 'b' => 'z']],
        ]);

        $this->assertSame(['a' => 'x'], $overrides->values);
        $this->assertSame([2 => ['b' => 'z']], $overrides->lines);
        $this->assertEquals(new AddendaOverrides(), AddendaOverrides::fromArray(['values' => 'x', 'lines' => null]));
    }

    public function testRoundTripsThroughAnArray(): void
    {
        $overrides = new AddendaOverrides(['remision' => 'REM01'], [1 => ['posicion' => '2']]);

        $this->assertEquals($overrides, AddendaOverrides::fromArray($overrides->toArray()));
    }
}
