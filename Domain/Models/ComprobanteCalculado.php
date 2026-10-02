<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ComprobanteCalculado
{
    /** @param ConceptoCalculado[] $conceptos */
    public function __construct(
        public readonly Comprobante $comprobante,
        public readonly array $conceptos,
        public readonly ImportesComprobante $importes,
        public readonly ImpuestosComprobante $impuestos,
    ) {}
}