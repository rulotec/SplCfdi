<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ImpuestoRetenidoResumen   // nivel comprobante: solo Impuesto e Importe
{
    public function __construct(
        public readonly string $impuesto,
        public readonly string $importe
    ) {}
}
