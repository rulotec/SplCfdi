<?php
declare(strict_types=1);

namespace SplCfdi\Infrastructure\Math;

use InvalidArgumentException;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Support\DecimalFormat;

final class BcMathDecimalMath implements DecimalMath
{
    public function __construct(
        private readonly DecimalConfiguration $configuration
    ) {}

    public function add(string $a, string $b): string
    {
        return bcadd(
            $a,
            $b,
            $this->configuration->calculationScale
            );
    }

    public function subtract(string $a, string $b): string
    {
        return bcsub(
            $a,
            $b,
            $this->configuration->calculationScale
            );
    }

    public function multiply(string $a, string $b): string
    {
        return bcmul(
            $a,
            $b,
            $this->configuration->calculationScale
            );
    }

    public function divide(string $a, string $b): string
    {
        if ($this->compare($b, '0') === 0) {
            throw new InvalidArgumentException(
                'No se puede dividir entre cero.'
                );
        }

        return bcdiv(
            $a,
            $b,
            $this->configuration->calculationScale
            );
    }

    public function compare(string $a, string $b): int
    {
        return bccomp(
            $a,
            $b,
            $this->configuration->calculationScale
            );
    }

    public function round(
        string $value,
        int $decimals
        ): string {
            $this->validateScale($decimals);

            DecimalFormat::assertValid($value, 'Valor', null, true);   // con signo, sin límite de decimales

            $negative = str_starts_with($value, '-');
            $absoluteValue = ltrim($value, '+-');


                [$integerPart, $fractionalPart] = array_pad(
                    explode('.', $absoluteValue, 2),
                    2,
                    ''
                    );
            /*
             * Ya tiene como máximo la precisión solicitada.
             */
            if (strlen($fractionalPart) <= $decimals) {
                return $this->formatRoundedValue(
                    $negative,
                    $integerPart,
                    str_pad(
                        $fractionalPart,
                        $decimals,
                        '0'
                        )
                    );
            }

            $kept = substr(
                $fractionalPart,
                0,
                $decimals
                );

            $nextDigit = (int) $fractionalPart[$decimals];

            if ($nextDigit < 5) {
                return $this->formatRoundedValue(
                    $negative,
                    $integerPart,
                    $kept
                    );
            }

            /*
             * Sumamos una unidad a la parte conservada.
             */
            if ($decimals === 0) {
                $roundedInteger = bcadd(
                    $integerPart,
                    '1',
                    0
                    );

                return ($negative ? '-' : '') . $roundedInteger;
            }

            $factor = bcpow(
                '10',
                (string) $decimals,
                0
                );

            $scaled = bcmul(
                $integerPart . '.' . $kept,
                $factor,
                0
                );

            $scaled = bcadd(
                $scaled,
                '1',
                0
                );

            $rounded = bcdiv(
                $scaled,
                $factor,
                $decimals
                );

            return ($negative ? '-' : '') . $rounded;
    }

    public function format(
        string $value,
        int $decimals
        ): string {
            $this->validateScale($decimals);

            return bcadd(
                $value,
                '0',
                $decimals
            );
    }

    private function validateScale(int $decimals): void
    {
        if ($decimals < 0) {
            throw new InvalidArgumentException(
                'La cantidad de decimales no puede ser negativa.'
            );
        }

        if ($decimals > $this->configuration->maximumScale) {
            throw new InvalidArgumentException(
                sprintf(
                    'La cantidad de decimales no puede ser mayor a %d.',
                    $this->configuration->maximumScale
                )
            );
        }
    }

    private function formatRoundedValue(
        bool $negative,
        string $integerPart,
        string $fractionalPart
    ): string {

        $sign = $negative ? '-' : '';

        if ($fractionalPart === '') {
            return $sign . $integerPart;
        }

        return $sign . $integerPart . '.' . $fractionalPart;
    }
}