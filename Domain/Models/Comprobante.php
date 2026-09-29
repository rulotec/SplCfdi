<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class Comprobante
{
    private ?ImportesComprobante $importes = null;
    private ?ImpuestosComprobante $impuestos = null;

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


    public function getImportes(): ImportesComprobante
    {
        return $this->importes;
    }

    public function getImpuestos(): ImpuestosComprobante
    {
        return $this->impuestos;
    }

    public function setImportesYImpuestos(
        ImportesComprobante $importes,
        ImpuestosComprobante $impuestos
    ): void {
        if ($this->importes !== null || $this->impuestos !== null) {
            throw new \LogicException(
                'Los importes e impuestos del comprobante ya fueron establecidos.'
            );
        }

        $this->importes = $importes;
        $this->impuestos = $impuestos;
    }
}
