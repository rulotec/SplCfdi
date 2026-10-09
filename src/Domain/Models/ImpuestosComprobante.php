<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ImpuestosComprobante
{
    /**
     * @param ImpuestoTrasladadoCalculado[] $traslados
     * @param ImpuestoRetenidoResumen[] $retenciones
     */
    public function __construct(
        public readonly array $traslados = [],
        public readonly array $retenciones = [],
        public readonly ?string $totalImpuestosTrasladados = null,
        public readonly ?string $totalImpuestosRetenidos = null
    ) {}
}
