<?php
declare(strict_types=1);

namespace SplCfdi\Domain\Configuration;

final class DecimalConfiguration
{
    public function __construct(
        public readonly int $calculationScale,
        public readonly int $maximumScale
    ) {}
}
