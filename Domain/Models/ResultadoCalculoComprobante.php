<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ResultadoCalculoComprobante
{
    public function __construct(
        public readonly ImportesComprobante $importes,
        public readonly ImpuestosComprobante $impuestos
    ) {}
}
