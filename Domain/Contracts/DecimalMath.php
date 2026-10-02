<?php

namespace SplCfdi\Domain\Contracts;

interface DecimalMath
{
    public function add(string $a, string $b): string;
    public function multiply(string $a, string $b): string;
    public function round(string $value, int $decimals): string;
    public function format(string $value, int $decimals): string;
    public function compare(string $a, string $b): int;
}