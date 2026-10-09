<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Math;

use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Support\DecimalFormat;

final class BcMathDecimalMath implements DecimalMath
{
    public function __construct(private readonly DecimalConfiguration $configuration) {}

    public function add(string $a, string $b): string
    {
        return bcadd($a, $b, $this->configuration->calculationScale);
    }

    public function subtract(string $a, string $b): string
    {
        return bcsub($a, $b, $this->configuration->calculationScale);
    }

    public function multiply(string $a, string $b): string
    {
        return bcmul($a, $b, $this->configuration->calculationScale);
    }

    public function compare(string $a, string $b): int
    {
        return bccomp($a, $b, $this->configuration->calculationScale);
    }

    /** Redondeo half-up sobre la magnitud (−1.005 → −1.01), sobre cadenas: sin floats. */
    public function round(string $value, int $decimals): string
    {
        $this->validateScale($decimals);
        DecimalFormat::assertValid($value, 'Valor', null, true);

        $negative = str_starts_with($value, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');

        // Dígitos conservados, sin punto decimal: "1.005" a 2 decimales → "100".
        $digits = $integer . substr(str_pad($fraction, $decimals, '0'), 0, $decimals);

        // Si el primer dígito descartado es ≥ 5, se suma una unidad al último conservado.
        if (strlen($fraction) > $decimals && (int) $fraction[$decimals] >= 5) {
            $digits = bcadd($digits, '1', 0);
        }

        return $this->compose($negative, $digits, $decimals);
    }

    public function format(string $value, int $decimals): string
    {
        $this->validateScale($decimals);

        return bcadd($value, '0', $decimals);
    }

    private function validateScale(int $decimals): void
    {
        DecimalFormat::assertScale($decimals, $this->configuration->maximumScale, 'La cantidad de decimales');
    }

    /** Reinserta el punto decimal; un resultado igual a cero nunca lleva signo. */
    private function compose(bool $negative, string $digits, int $decimals): string
    {
        $digits = str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);
        $split = strlen($digits) - $decimals;

        $integer = substr($digits, 0, $split);
        $fraction = substr($digits, $split);
        $sign = $negative && trim($digits, '0') !== '' ? '-' : '';

        return $sign . $integer . ($decimals > 0 ? '.' . $fraction : '');
    }
}