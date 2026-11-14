<?php

declare(strict_types=1);

namespace SplCfdi\Application\AddendaForm;

/**
 * What the user typed in the addenda form for one invoice. A field that is present overrides its default;
 * an empty string clears it. The host stores it as it comes from toArray() and reads it back with fromArray().
 */
final class AddendaOverrides
{
    /**
     * @param array<string, string>             $values field name => value
     * @param array<int, array<string, string>> $lines  line index (from 0) => field name => value
     */
    public function __construct(
        public readonly array $values = [],
        public readonly array $lines = [],
    ) {}

    /**
     * From what the form sent or the host stored: {"values": {name: text}, "lines": {index: {name: text}}}.
     * It comes from outside, so anything that isn't text under a field name is dropped.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $lines = [];
        foreach (is_array($data['lines'] ?? null) ? $data['lines'] : [] as $index => $values) {
            $index = filter_var($index, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $values = is_array($values) ? self::texts($values) : [];
            if ($index !== false && $values !== []) {
                $lines[$index] = $values;
            }
        }

        return new self(self::texts(is_array($data['values'] ?? null) ? $data['values'] : []), $lines);
    }

    /** @return array{values: array<string, string>, lines: array<int, array<string, string>>} */
    public function toArray(): array
    {
        return ['values' => $this->values, 'lines' => $this->lines];
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, string>
     */
    private static function texts(array $values): array
    {
        return array_filter($values, fn ($value, $name) => is_string($name) && is_string($value), ARRAY_FILTER_USE_BOTH);
    }
}
