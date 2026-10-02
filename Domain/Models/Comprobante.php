<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class Comprobante
{
    /**
     * @param Concepto[] $conceptos
     */
    public function __construct(
        public readonly string $version,
        public readonly string $fecha,
        public readonly string $moneda,
        public readonly string $tipoDeComprobante,
        public readonly string $exportacion,
        public readonly string $lugarExpedicion,
        public readonly Emisor $emisor,
        public readonly Receptor $receptor,
        public readonly array $conceptos,
        public readonly ?string $serie = null,
        public readonly ?string $folio = null,
        public readonly ?string $metodoPago = null,
        public readonly ?string $formaPago = null
    ) {}
}
