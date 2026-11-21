<?php

declare(strict_types=1);

namespace SplCfdi\Application\AddendaForm;

use SplCfdi\Domain\Addendas\{Addenda, NivelCampo};
use SplCfdi\Domain\Models\Comprobante;

/**
 * The values the host already has for an addenda (constants, fields of its own records). Same shape as
 * DatosAddenda: a Concepto field in $values applies to every line that doesn't have its own.
 * What neither has comes from $fallback (see ofAddenda()).
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
        public readonly ?AddendaDefaults $fallback = null,
    ) {}

    /**
     * What the addenda itself uses for the fields left empty (Addenda::porDefecto()), with its
     * CampoAddenda::$porDefecto as the source, e.g. "La Moneda del CFDI".
     */
    public static function ofAddenda(Addenda $addenda, Comprobante $comprobante): self
    {
        $datos = $addenda->porDefecto($comprobante);
        $values = [];
        $lines = [];
        foreach ($addenda->campos() as $campo) {
            if ($campo->nivel !== NivelCampo::Concepto) {
                $value = $datos->valor($campo->nombre);
                $values += $value === null ? [] : [$campo->nombre => new DefaultValue($value, $campo->porDefecto)];
                continue;
            }
            for ($i = 0; $i < count($comprobante->conceptos); $i++) {
                $value = $datos->valorConcepto($i, $campo->nombre);
                $lines[$i] ??= [];
                $lines[$i] += $value === null ? [] : [$campo->nombre => new DefaultValue($value, $campo->porDefecto)];
            }
        }

        return new self($values, $lines);
    }

    /** These defaults, and $fallback for what they don't have. */
    public function withFallback(self $fallback): self
    {
        return new self($this->values, $this->lines, $this->fallback?->withFallback($fallback) ?? $fallback);
    }

    public function value(string $name): ?DefaultValue
    {
        return $this->values[$name] ?? $this->fallback?->value($name);
    }

    public function line(int $index, string $name): ?DefaultValue
    {
        return $this->lines[$index][$name] ?? $this->values[$name] ?? $this->fallback?->line($index, $name);
    }
}
