<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ImpuestoRetenidoCalculado   // nivel concepto
{
    public function __construct(
        public readonly string $base,
        public readonly string $impuesto,
        public readonly TipoFactor $tipoFactor,
        public readonly string $tasaOCuota,
        public readonly string $importe
        ) {}
}

