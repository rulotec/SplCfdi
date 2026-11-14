<?php

declare(strict_types=1);

namespace SplCfdi\Application\AddendaForm;

use SplCfdi\Domain\Addendas\{Addenda, DatosAddenda, NivelCampo};

/**
 * The value of each addenda field for one invoice: what the user typed or, if nothing, the default. The
 * host uses datos() to generate the CFDI; the form shows each Cell.
 */
final class AddendaValues
{
    public function __construct(
        private readonly Addenda $addenda,
        private readonly AddendaDefaults $defaults,
        private readonly AddendaOverrides $overrides,
        private readonly int $lineCount,
    ) {}

    /** A Cliente or Comprobante field. */
    public function header(string $name): Cell
    {
        return self::cell($this->defaults->value($name), $this->overrides->values[$name] ?? null);
    }

    /** A Concepto field of line $index (from 0). */
    public function line(int $index, string $name): Cell
    {
        return self::cell($this->defaults->line($index, $name), $this->overrides->lines[$index][$name] ?? null);
    }

    /** What goes in Comprobante::$addenda. */
    public function datos(): DatosAddenda
    {
        $values = [];
        $lines = [];
        foreach ($this->addenda->campos() as $campo) {
            if ($campo->nivel !== NivelCampo::Concepto) {
                $values[$campo->nombre] = $this->header($campo->nombre)->value;
                continue;
            }
            for ($i = 0; $i < $this->lineCount; $i++) {
                $lines[$i][$campo->nombre] = $this->line($i, $campo->nombre)->value;
            }
        }

        return new DatosAddenda(
            $this->addenda->clave(),
            self::filled($values),
            array_filter(array_map(self::filled(...), $lines)),
        );
    }

    private static function cell(?DefaultValue $default, ?string $typed): Cell
    {
        $overridden = $typed !== null && $typed !== $default?->value;
        $value = $overridden ? $typed : $default?->value;

        return new Cell(
            $value === null || trim($value) === '' ? null : $value,
            $default?->value,
            $default?->source,
            $overridden,
        );
    }

    /**
     * @param array<string, ?string> $values
     *
     * @return array<string, string>
     */
    private static function filled(array $values): array
    {
        return array_filter($values, fn (?string $value) => $value !== null);
    }
}
