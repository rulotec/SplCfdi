<?php

declare(strict_types=1);

namespace SplCfdi\Application;

use SplCfdi\Domain\Models\ComprobanteCalculado;

final class GeneratedCfdi
{
    public function __construct(
        public readonly ComprobanteCalculado $calculado,
        public readonly string $xml,
    ) {}
}
