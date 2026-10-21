<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class Comprobante
{
    /** Formato de Fecha en el XML: hora local, sin zona horaria. */
    public const FORMATO_FECHA = 'Y-m-d\TH:i:s';

    /**
     * @param string             $moneda           c_Moneda (p. ej. "MXN"); sus decimales salen del catálogo
     * @param Concepto[]         $conceptos
     * @param string|null        $tipoCambio       requerido si la moneda no es MXN ni XXX
     * @param string|null        $confirmacion     clave del PAC cuando TipoCambio o Total exceden los límites del SAT
     * @param CfdiRelacionados[] $cfdiRelacionados un nodo por TipoRelacion
     */
    public function __construct(
        public readonly string $version,
        public readonly \DateTimeImmutable $fecha,
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
        public readonly ?string $formaPago = null,
        public readonly ?InformacionGlobal $informacionGlobal = null,
        public readonly ?string $tipoCambio = null,
        public readonly ?string $confirmacion = null,
        public readonly array $cfdiRelacionados = [],
    ) {}

    /** La Fecha tal como aparece en el XML: hora local, sin zona horaria. */
    public function fechaCfdi(): string
    {
        return $this->fecha->format(self::FORMATO_FECHA);
    }
}
