<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ImpuestoTrasladado
{
    public function __construct(
        public readonly string $impuesto,
        public readonly TipoFactor $tipoFactor,
        public readonly ?string $tasaOCuota = null,   // obligatoria con Tasa, ausente con Exento
    ) {}
}
