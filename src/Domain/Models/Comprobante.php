<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class Comprobante
{
    /** Formato de Fecha en el XML: hora local, sin zona horaria. */
    public const FORMATO_FECHA = 'Y-m-d\TH:i:s';

    /**
     * @param Concepto[] $conceptos
     */
    public function __construct(
        public readonly string $version,
        public readonly \DateTimeImmutable $fecha,
        public readonly Moneda $moneda,
        public readonly string $tipoDeComprobante,
        public readonly string $exportacion,
        public readonly string $lugarExpedicion,
        public readonly Emisor $emisor,
        public readonly Receptor $receptor,
        public readonly array $conceptos,
        public readonly ?string $serie = null,
        public readonly ?string $folio = null,
        public readonly ?string $metodoPago = null,
        public readonly ?string $formaPago = null,
        public readonly ?InformacionGlobal $informacionGlobal = null
    ) {}

    /** La Fecha tal como aparece en el XML: hora local, sin zona horaria. */
    public function fechaCfdi(): string
    {
        return $this->fecha->format(self::FORMATO_FECHA);
    }
}
