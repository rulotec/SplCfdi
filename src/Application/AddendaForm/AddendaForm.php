<?php

declare(strict_types=1);

namespace SplCfdi\Application\AddendaForm;

use SplCfdi\Domain\Addendas\{Addenda, CampoAddenda, DatosAddenda, NivelCampo};
use SplCfdi\Domain\Models\{ComprobanteCalculado, Concepto};
use SplCfdi\Domain\Rules\Violation;

/**
 * Everything the addenda form page needs, as returned by AddendaFormService::build(). toArray() is the
 * contract with the page (JSON); docs/addendas.md describes it.
 *
 * @phpstan-type CellArray array{value: ?string, default: ?string, source: ?string, overridden: bool}
 * @phpstan-type FieldArray array{name: string, label: string, level: string, type: string, required: bool,
 *     minLength: ?int, maxLength: ?int, pattern: ?string, options: list<array{value: string, label: string}>,
 *     help: ?string, fallback: ?string}
 * @phpstan-type LineArray array{index: int, number: int, productKey: string, description: string, quantity: string,
 *     unit: string, unitPrice: string, amount: ?string, notice: ?string, values: array<string, CellArray>}
 * @phpstan-type ViolationArray array{code: string, message: string, path: ?string, field: ?string, line: ?int}
 * @phpstan-type FormArray array{addenda: array{key: string, name: ?string}, fields: list<FieldArray>,
 *     header: array<string, CellArray>, lines: list<LineArray>, violations: list<ViolationArray>, valid: bool}
 */
final class AddendaForm
{
    private const LINE_PATH = '/^Addenda\.Concepto\[(\d+)\]\.(\w+)$/D';
    private const HEADER_PATH = '/^Addenda\.(\w+)$/D';

    /** @var list<Concepto> */
    private readonly array $conceptos;

    /**
     * @param Addenda|null              $addenda     null if $key isn't a known addenda
     * @param Concepto[]                $conceptos   the invoice lines, in Comprobante::$conceptos order
     * @param array<int, string>        $lineNotices line index (from 0) => what the host wants the user to check
     * @param Violation[]               $violations  of the addenda, or of the invoice when it can't be calculated
     * @param ComprobanteCalculado|null $calculado   null until checked, or when the invoice can't be calculated
     */
    public function __construct(
        public readonly string $key,
        public readonly ?Addenda $addenda,
        private readonly ?AddendaValues $values,
        array $conceptos,
        private readonly array $lineNotices = [],
        public readonly array $violations = [],
        private readonly ?ComprobanteCalculado $calculado = null,
    ) {
        $this->conceptos = array_values($conceptos);
    }

    /** @param Violation[] $violations */
    public function checked(array $violations, ?ComprobanteCalculado $calculado): self
    {
        return new self($this->key, $this->addenda, $this->values, $this->conceptos, $this->lineNotices, $violations, $calculado);
    }

    /** What goes in Comprobante::$addenda; with an unknown key, just the key (RuleAddenda reports it). */
    public function datos(): DatosAddenda
    {
        return $this->values?->datos() ?? new DatosAddenda($this->key);
    }

    /** @return FormArray */
    public function toArray(): array
    {
        $campos = $this->addenda?->campos() ?? [];
        $header = array_filter($campos, fn (CampoAddenda $c) => $c->nivel !== NivelCampo::Concepto);
        $line = array_filter($campos, fn (CampoAddenda $c) => $c->nivel === NivelCampo::Concepto);

        return [
            'addenda' => ['key' => $this->key, 'name' => $this->addenda?->nombre()],
            'fields' => array_values(array_map(self::field(...), $campos)),
            'header' => $this->cells($header, fn (string $name) => $this->values?->header($name)),
            'lines' => array_map(fn (int $index, Concepto $concepto) => [
                'index' => $index,
                'number' => $index + 1,
                'productKey' => $concepto->claveProdServ,
                'description' => $concepto->descripcion,
                'quantity' => $concepto->cantidad,
                'unit' => $concepto->unidad ?? $concepto->claveUnidad,
                'unitPrice' => $concepto->valorUnitario,
                'amount' => ($this->calculado?->conceptos[$index] ?? null)?->importe,
                'notice' => $this->lineNotices[$index] ?? null,
                'values' => $this->cells($line, fn (string $name) => $this->values?->line($index, $name)),
            ], array_keys($this->conceptos), $this->conceptos),
            'violations' => array_values(array_map($this->violation(...), $this->violations)),
            'valid' => $this->violations === [],
        ];
    }

    /** @return FieldArray */
    private static function field(CampoAddenda $campo): array
    {
        return [
            'name' => $campo->nombre,
            'label' => $campo->etiqueta,
            'level' => $campo->nivel->value,
            'type' => $campo->tipo->value,
            'required' => $campo->requerido,
            'minLength' => $campo->longitudMinima,
            'maxLength' => $campo->longitudMaxima,
            'pattern' => $campo->patron,
            'options' => array_map(
                fn (string $value, string $label) => ['value' => $value, 'label' => $label],
                array_keys($campo->opciones),
                array_values($campo->opciones),
            ),
            'help' => $campo->ayuda,
            'fallback' => $campo->porDefecto,
        ];
    }

    /**
     * @param CampoAddenda[]                $campos
     * @param callable(string): (Cell|null) $cell
     *
     * @return array<string, CellArray>
     */
    private function cells(array $campos, callable $cell): array
    {
        $cells = [];
        foreach ($campos as $campo) {
            $cells[$campo->nombre] = ($cell($campo->nombre) ?? new Cell(null, null, null, false))->toArray();
        }

        return $cells;
    }

    /**
     * field and line say which input the violation belongs to; both null when it has no input in the form
     * (an invoice problem, or a path inside the addenda's XML from its XSD).
     *
     * @return ViolationArray
     */
    private function violation(Violation $v): array
    {
        $field = null;
        $line = null;
        $names = array_map(fn (CampoAddenda $c) => $c->nombre, $this->addenda?->campos() ?? []);

        if (preg_match(self::LINE_PATH, (string) $v->campo, $m) === 1 && in_array($m[2], $names, true)) {
            [$line, $field] = [(int) $m[1] - 1, $m[2]];
        } elseif (preg_match(self::HEADER_PATH, (string) $v->campo, $m) === 1 && in_array($m[1], $names, true)) {
            $field = $m[1];
        }

        return ['code' => $v->code, 'message' => $v->message, 'path' => $v->campo, 'field' => $field, 'line' => $line];
    }
}
