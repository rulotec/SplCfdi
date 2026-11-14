<?php

declare(strict_types=1);

namespace SplCfdi\Addendas;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Addendas\{CampoAddenda, DatosAddenda, NivelCampo, TipoCampo};
use SplCfdi\Domain\Models\{ComprobanteCalculado, ConceptoCalculado};

/** Metalsa, addenda MPNP (proveedor no productivo), versión 1.0. */
final class MetalsaMpnp extends AddendaBase
{
    private const TIPOS_DOCUMENTO = ['I' => 'FA', 'E' => 'CR'];

    public function clave(): string
    {
        return 'MPNP';
    }

    public function nombre(): string
    {
        return 'Metalsa – Proveedor no productivo (MPNP)';
    }

    public function namespace(): string
    {
        return 'http://www.metalsa.com.mx/Addenda/MPNP';
    }

    public function campos(): array
    {
        return [
            new CampoAddenda('codigoProveedor', 'Código de proveedor', NivelCampo::Cliente, longitudMaxima: 13,
                porDefecto: 'El RFC del emisor'),
            new CampoAddenda('segmentoProducto', 'Segmento de producto', NivelCampo::Cliente, longitudMaxima: 4),
            $this->campoTipoDocumento(['FA' => 'Factura', 'CR' => 'Nota de crédito'], 'FA en Ingreso, CR en Egreso'),
            new CampoAddenda('cancelaSustituye', 'Factura que cancela y sustituye', NivelCampo::Comprobante, longitudMaxima: 15),
            new CampoAddenda('referenciaProveedor', 'Referencia del proveedor', NivelCampo::Comprobante, longitudMaxima: 15),
            new CampoAddenda('destinoCodigo', 'Código de la planta destino', NivelCampo::Comprobante, requerido: true, longitudMaxima: 13),
            new CampoAddenda('destinoNombre', 'Nombre de la planta destino', NivelCampo::Comprobante, requerido: true, longitudMaxima: 256),
            new CampoAddenda('cuentaCorreo', 'Correo del receptor en Metalsa', NivelCampo::Comprobante, requerido: true, longitudMaxima: 60),
            new CampoAddenda('notaFactura', 'Nota de la factura', NivelCampo::Comprobante),
            new CampoAddenda('ordenCompra', 'Orden de compra', NivelCampo::Concepto, TipoCampo::Entero, true,
                patron: '[0-9]{1,8}', ayuda: 'hasta ocho dígitos'),
            new CampoAddenda('numeroParte', 'Número de parte del proveedor', NivelCampo::Concepto, requerido: true, longitudMaxima: 30),
            new CampoAddenda('fechaRecibo', 'Fecha de recibo en planta', NivelCampo::Concepto, requerido: true,
                patron: '[0-9]{4}-[0-9]{2}-[0-9]{2}', ayuda: 'AAAA-MM-DD'),
            new CampoAddenda('numeroRecibo', 'Número de recibo en planta', NivelCampo::Concepto, requerido: true,
                longitudMaxima: 10, patron: '[a-zA-Z0-9]+', ayuda: 'solo letras y números'),
            new CampoAddenda('unidadMedida', 'Unidad de medida', NivelCampo::Concepto, longitudMaxima: 4,
                porDefecto: 'La Unidad del concepto si tiene hasta 4 caracteres; si no, su ClaveUnidad'),
            new CampoAddenda('nota', 'Nota', NivelCampo::Concepto),
        ];
    }

    public function reglas(ComprobanteCalculado $calculado, DatosAddenda $datos): array
    {
        return [
            ...$this->reglaTipoDocumento($calculado, $datos, self::TIPOS_DOCUMENTO),
            ...$this->reglaFolio($calculado, 20, 30),
        ];
    }

    public function crear(DOMDocument $dom, ComprobanteCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $c = $calculado->comprobante;
        $cancela = $datos->valor('cancelaSustituye');
        $segmento = $datos->valor('segmentoProducto');

        return $this->nodo($dom, 'factura', [
            'tipoDocumento' => 'MPNP',
            'tipoDocumentoFiscal' => $this->tipoDocumento($calculado, $datos, self::TIPOS_DOCUMENTO),
            'version' => '1.0',
            'serie' => $c->serie,
            'folioFiscal' => $c->folio,
            'fecha' => $c->fecha->format('Y-m-d'),
            'referenciaProveedor' => $datos->valor('referenciaProveedor'),
        ], [
            $cancela === null ? null : $this->nodo($dom, 'cancelaciones', ['cancelaSustituye' => $cancela]),
            $this->nodo($dom, 'moneda', ['tipoMoneda' => $c->moneda]),
            $this->nodo($dom, 'proveedor', [
                'codigo' => $datos->valor('codigoProveedor') ?? $c->emisor->rfc,
                'nombre' => $this->recortar($c->emisor->nombre, 256),
            ]),
            $this->nodo($dom, 'destino', [
                'codigo' => $datos->valor('destinoCodigo'),
                'nombre' => $datos->valor('destinoNombre'),
            ]),
            ...$this->notas($dom, 'nota', $datos->valor('notaFactura'), 200),
            $this->nodo($dom, 'cuentaCorreo', ['cuentaCorreo' => $datos->valor('cuentaCorreo')]),
            $segmento === null ? null : $this->nodo($dom, 'segmentoProducto', ['segmentoProducto' => $segmento]),
            $this->nodo($dom, 'partes', [], array_map(
                fn (int $i, ConceptoCalculado $concepto) => $this->parte($dom, $i, $concepto, $datos),
                array_keys($calculado->conceptos),
                $calculado->conceptos,
            )),
        ]);
    }

    private function parte(DOMDocument $dom, int $i, ConceptoCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $concepto = $calculado->concepto;

        return $this->nodo($dom, 'part', [
            'numeroLinea' => (string) ($i + 1),
            'numeroParte' => $datos->valorConcepto($i, 'numeroParte'),
            'cantidad' => $concepto->cantidad,
            'unidadMedida' => $datos->valorConcepto($i, 'unidadMedida') ?? $this->unidad($concepto, 4),
            'precioUnitario' => $concepto->valorUnitario,
            'fechaRecibo' => $datos->valorConcepto($i, 'fechaRecibo'),
            'numeroRecibo' => $datos->valorConcepto($i, 'numeroRecibo'),
            'descripcion' => $this->recortar($concepto->descripcion, 200),
            'ordencompra' => $datos->valorConcepto($i, 'ordenCompra'),
        ], $this->notas($dom, 'nota', $datos->valorConcepto($i, 'nota'), 200));
    }
}
