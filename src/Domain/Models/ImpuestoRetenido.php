<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ImpuestoRetenido
{
    public function __construct(
        public readonly string $impuesto,
        public readonly TipoFactor $tipoFactor,   // una retención no puede ser Exento
        public readonly string $tasaOCuota,
    ) {}
}
