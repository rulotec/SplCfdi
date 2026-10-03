<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ImpuestosComprobante
{
    /**
     * @param ImpuestoTrasladadoCalculado[] $traslados
     */
    public function __construct(
        public readonly array $traslados = [],
        public readonly ?string $totalImpuestosTrasladados = null,
        public readonly ?string $totalImpuestosRetenidos = null
    ) {}
}
