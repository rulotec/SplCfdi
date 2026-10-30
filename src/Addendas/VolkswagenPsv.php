<?php

declare(strict_types=1);

namespace SplCfdi\Addendas;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Addendas\{CampoAddenda, DatosAddenda, NivelCampo, TipoCampo};
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Models\{ComprobanteCalculado, ConceptoCalculado};

/** Volkswagen de México, addenda PSV (servicios), versión 1.0. */
final class VolkswagenPsv extends AddendaBase
{
    private const TIPOS_DOCUMENTO = ['I' => 'FA', 'E' => 'CR'];

    /** @param int $longitudNota caracteres por nodo Nota (el XSD admite 254); las más largas se reparten */
    public function __construct(DecimalMath $math, private readonly int $longitudNota = 254)
    {
        parent::__construct($math);
    }

    public function clave(): string
    {
        return 'PSV';
    }

    public function nombre(): string
    {
        return 'Volkswagen de México – Servicios (PSV)';
    }

    public function namespace(): string
    {
        return 'http://www.vwnovedades.com/volkswagen/kanseilab/shcp/2009/Addenda/PSV';
    }

    public function campos(): array
    {
        return [
            new CampoAddenda('division', 'División', NivelCampo::Cliente, TipoCampo::Opcion, true,
                opciones: ['VW' => 'Volkswagen México', 'INFODE' => 'INFODE', 'VWSP' => 'Volkswagen Servicios Profesionales']),
            new CampoAddenda('codigoProveedor', 'Código de proveedor', NivelCampo::Cliente, requerido: true,
                patron: '[0-9]{10}', ayuda: 'diez dígitos, asignado por VW'),
            new CampoAddenda('correoContacto', 'Correo de contacto del proveedor', NivelCampo::Cliente, requerido: true, longitudMaxima: 80),
            new CampoAddenda('codigoImpuesto', 'Código de impuesto', NivelCampo::Cliente, requerido: true,
                longitudMinima: 2, longitudMaxima: 2, ayuda: 'dos caracteres, viene en la orden de compra (p. ej. 1A)'),
            new CampoAddenda('tipoMoneda', 'Moneda', NivelCampo::Cliente, longitudMinima: 3, longitudMaxima: 5,
                ayuda: 'solo si VW pide una clave distinta a la del CFDI (p. ej. MXP)', porDefecto: 'La Moneda del CFDI'),
            $this->campoTipoDocumento(['FA' => 'Factura', 'CA' => 'Nota de cargo', 'CR' => 'Nota de crédito'], 'FA en Ingreso, CR en Egreso'),
            new CampoAddenda('cancelaSustituye', 'Factura que cancela y sustituye', NivelCampo::Comprobante, longitudMaxima: 16),
            new CampoAddenda('remision', 'Remisión', NivelCampo::Comprobante, longitudMaxima: 16),
            new CampoAddenda('solicitanteNombre', 'Nombre del solicitante en VW', NivelCampo::Comprobante, requerido: true, longitudMaxima: 80),
            new CampoAddenda('solicitanteCorreo', 'Correo del solicitante en VW', NivelCampo::Comprobante, requerido: true, longitudMaxima: 80),
            new CampoAddenda('ordenCompra', 'Orden de compra', NivelCampo::Concepto, requerido: true, longitudMinima: 10, longitudMaxima: 12),
            new CampoAddenda('posicion', 'Posición en la orden de compra', NivelCampo::Concepto, TipoCampo::Entero, true),
            new CampoAddenda('numeroMaterial', 'Número de material', NivelCampo::Concepto, longitudMaxima: 18),
            new CampoAddenda('unidadMedida', 'Unidad de medida', NivelCampo::Concepto, longitudMaxima: 3,
                porDefecto: 'La Unidad del concepto si tiene hasta 3 caracteres; si no, su ClaveUnidad'),
            new CampoAddenda('nota', 'Nota', NivelCampo::Concepto),
        ];
    }

    public function reglas(ComprobanteCalculado $calculado, DatosAddenda $datos): array
    {
        return $this->reglaTipoDocumento($calculado, $datos, self::TIPOS_DOCUMENTO);
    }

    public function crear(DOMDocument $dom, ComprobanteCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $c = $calculado->comprobante;
        $codigoImpuesto = $datos->valor('codigoImpuesto');
        $cancela = $datos->valor('cancelaSustituye');
        $referencia = $this->recortar(($c->serie ?? '') . ($c->folio ?? ''), 16);

        return $this->nodo($dom, 'Factura', [
            'version' => '1.0',
            'tipoDocumentoFiscal' => $this->tipoDocumento($calculado, $datos, self::TIPOS_DOCUMENTO),
            'tipoDocumentoVWM' => 'PSV',
            'division' => $datos->valor('division'),
        ], [
            $cancela === null ? null : $this->nodo($dom, 'Cancelaciones', ['cancelaSustituye' => $cancela]),
            $this->nodo($dom, 'Moneda', [
                'tipoMoneda' => $datos->valor('tipoMoneda') ?? $c->moneda,
                'tipoCambio' => $this->decimales($c->tipoCambio ?? '1', 4),
                'codigoImpuesto' => $codigoImpuesto,
            ]),
            $this->nodo($dom, 'Proveedor', [
                'codigo' => $datos->valor('codigoProveedor'),
                'nombre' => $this->recortar($c->emisor->nombre, 35),
                'correoContacto' => $datos->valor('correoContacto'),
            ]),
            $this->nodo($dom, 'Referencias', [
                'referenciaProveedor' => $referencia === '' ? null : $referencia,
                'remision' => $datos->valor('remision'),
            ]),
            $this->nodo($dom, 'Solicitante', [
                'nombre' => $datos->valor('solicitanteNombre'),
                'correo' => $datos->valor('solicitanteCorreo'),
            ]),
            $this->nodo($dom, 'Partes', [], array_map(
                fn (int $i, ConceptoCalculado $concepto) => $this->parte($dom, $i, $concepto, $datos, $codigoImpuesto),
                array_keys($calculado->conceptos),
                $calculado->conceptos,
            )),
        ]);
    }

    private function parte(DOMDocument $dom, int $i, ConceptoCalculado $calculado, DatosAddenda $datos, ?string $codigoImpuesto): DOMElement
    {
        $concepto = $calculado->concepto;

        return $this->nodo($dom, 'Parte', [
            'posicion' => $datos->valorConcepto($i, 'posicion'),
            'numeroMaterial' => $datos->valorConcepto($i, 'numeroMaterial'),
            'descripcionMaterial' => $this->recortar($concepto->descripcion, 40),
            'cantidadMaterial' => $this->decimales($concepto->cantidad, 4),
            'unidadMedida' => $datos->valorConcepto($i, 'unidadMedida') ?? $this->unidad($concepto, 3),
            'precioUnitario' => $this->decimales($concepto->valorUnitario, 5),
            'montoLinea' => $this->decimales($calculado->importe, 2),
            'codigoImpuesto' => $codigoImpuesto,
        ], [
            $this->nodo($dom, 'Referencias', ['ordenCompra' => $datos->valorConcepto($i, 'ordenCompra')]),
            ...$this->notas($dom, 'Nota', $datos->valorConcepto($i, 'nota'), $this->longitudNota),
        ]);
    }
}
