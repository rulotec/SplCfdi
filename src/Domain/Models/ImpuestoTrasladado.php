<?php
declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ImpuestoTrasladado
{
    public function __construct(
        public readonly string $impuesto,
        public readonly string $tipoFactor,
        public readonly string $tasaOCuota
    ) {}
}
