<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ImpuestoTrasladadoCalculado
{
    public function __construct(
        public readonly string $base,
        public readonly string $impuesto,
        public readonly string $tipoFactor,
        public readonly string $tasaOCuota,
        public readonly string $importe
    ) {}
}
