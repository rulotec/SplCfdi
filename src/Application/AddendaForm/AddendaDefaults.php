<?php

declare(strict_types=1);

namespace SplCfdi\Application\AddendaForm;

/**
 * The values the host already has for an addenda (constants, fields of its own records). Same shape as
 * DatosAddenda: a Concepto field in $values applies to every line that doesn't have its own.
 */
final class AddendaDefaults
{
    /**
     * @param array<string, DefaultValue>             $values field name => default
     * @param array<int, array<string, DefaultValue>> $lines  line index (from 0, as in Comprobante::$conceptos) => field name => default
     */
    public function __construct(
        public readonly array $values = [],
        public readonly array $lines = [],
    ) {}

    public function value(string $name): ?DefaultValue
    {
        return $this->values[$name] ?? null;
    }

    public function line(int $index, string $name): ?DefaultValue
    {
        return $this->lines[$index][$name] ?? $this->value($name);
    }
}
