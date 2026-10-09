<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Support;

final class DecimalFormat
{
    /** @param int|null $maxDecimals null = sin límite de decimales */
    public static function matches(string $value, ?int $maxDecimals = null, bool $allowNegative = false): bool
    {
        if ($maxDecimals !== null && $maxDecimals < 0) {
            throw new \InvalidArgumentException('El máximo de decimales no puede ser negativo.');
        }

        $fraction = match (true) {
            $maxDecimals === null => '(?:\.\d+)?',
            $maxDecimals === 0    => '',
            default               => sprintf('(?:\.\d{1,%d})?', $maxDecimals),
        };

        $pattern = sprintf('/^%s\d+%s$/D', $allowNegative ? '-?' : '', $fraction);

        return preg_match($pattern, $value) === 1;
    }

    /** @throws \InvalidArgumentException */
    public static function assertValid(
        string $value,
        string $field,
        ?int $maxDecimals = null,
        bool $allowNegative = false
    ): void {
        if (self::matches($value, $maxDecimals, $allowNegative)) {
            return;
        }

        throw new \InvalidArgumentException(sprintf(
            '%s inválido: "%s" (%s%s).',
            $field,
            $value,
            $allowNegative ? 'decimal' : 'decimal no negativo',
            $maxDecimals === null ? '' : sprintf(', máximo %d decimales', $maxDecimals)
        ));
    }

    /** @throws \InvalidArgumentException si $scale no está entre 0 y $max */
    public static function assertScale(int $scale, int $max, string $label): void
    {
        if ($scale < 0 || $scale > $max) {
            throw new \InvalidArgumentException(sprintf('%s debe estar entre 0 y %d.', $label, $max));
        }
    }
}
