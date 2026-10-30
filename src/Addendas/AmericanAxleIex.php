<?php

declare(strict_types=1);

namespace SplCfdi\Addendas;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Addendas\{CampoAddenda, DatosAddenda, NivelCampo, TipoCampo};
use SplCfdi\Domain\Models\{ComprobanteCalculado, ConceptoCalculado};

/** American Axle & Manufacturing (AAM), addenda IEX, versión 1.1. */
final class AmericanAxleIex extends AddendaBase
{
    private const TIPOS_DOCUMENTO = ['I' => 'INV', 'E' => 'CRM'];

    public function clave(): string
    {
        return 'IEX';
    }

    public function nombre(): string
    {
        return 'American Axle (IEX)';
    }

    public function namespace(): string
    {
        return 'https://online.aam.com/Addenda/IEX';
    }

    public function campos(): array
    {
        return [
            new CampoAddenda('codigoProveedor', 'Código de proveedor', NivelCampo::Cliente, ayuda: 'asignado por AAM'),
            new CampoAddenda('clabe', 'CLABE para el pago', NivelCampo::Cliente, requerido: true),
            new CampoAddenda('nombreBanco', 'Banco de la CLABE', NivelCampo::Cliente, requerido: true),
            $this->campoTipoDocumento(
                ['INV' => 'Factura', 'CRM' => 'Nota de crédito', 'DRM' => 'Nota de cargo', 'HON' => 'Honorarios', 'ARR' => 'Arrendamiento'],
                'INV en Ingreso, CRM en Egreso',
            ),
            new CampoAddenda('organizacionOperacional', 'Organización operacional', NivelCampo::Comprobante, requerido: true,
                ayuda: 'viene en la orden de compra (LDI, LMI, QDI, QMI, GDI, GMI…)'),
            new CampoAddenda('requisitorNombre', 'Nombre del requisitor en AAM', NivelCampo::Comprobante, requerido: true),
            new CampoAddenda('requisitorCorreo', 'Correo del requisitor en AAM', NivelCampo::Comprobante, requerido: true),
            new CampoAddenda('ordenCompra', 'Orden de compra', NivelCampo::Concepto),
            new CampoAddenda('requisicion', 'Requisición', NivelCampo::Concepto),
            new CampoAddenda('referenciaProveedor', 'Número de producto del proveedor', NivelCampo::Concepto),
            new CampoAddenda('numero', 'Número de parte en la orden de compra', NivelCampo::Concepto),
            new CampoAddenda('unidadDeMedida', 'Unidad de medida', NivelCampo::Concepto,
                porDefecto: 'La Unidad del concepto o, si no tiene, su ClaveUnidad'),
            new CampoAddenda('nota', 'Nota', NivelCampo::Concepto, longitudMaxima: 200),
        ];
    }

    public function reglas(ComprobanteCalculado $calculado, DatosAddenda $datos): array
    {
        return $this->reglaTipoDocumento($calculado, $datos, self::TIPOS_DOCUMENTO);
    }

    public function crear(DOMDocument $dom, ComprobanteCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $c = $calculado->comprobante;

        return $this->nodo($dom, 'Factura', [
            'tipoDocumento' => 'IEX',
            'TipoDocumentoFiscal' => $this->tipoDocumento($calculado, $datos, self::TIPOS_DOCUMENTO),
            'OrganizacionOperacional' => $datos->valor('organizacionOperacional'),
            'version' => '1.1',
            'Clabe' => $datos->valor('clabe'),
            'NombreBanco' => $datos->valor('nombreBanco'),
        ], [
            $this->nodo($dom, 'Moneda', [
                'tipoMoneda' => $c->moneda,
                'tipoCambio' => $this->decimales($c->tipoCambio ?? '1', 4),
            ]),
            // El XSD exige el atributo aunque AAM no siempre asigna código.
            $this->nodo($dom, 'Proveedor', ['Codigo' => $datos->valor('codigoProveedor') ?? '']),
            $this->nodo($dom, 'Requisitor', [
                'Nombre' => $datos->valor('requisitorNombre'),
                'Correo' => $datos->valor('requisitorCorreo'),
            ]),
            $this->nodo($dom, 'Conceptos', [], array_map(
                fn (int $i, ConceptoCalculado $concepto) => $this->parte($dom, $i, $concepto, $datos),
                array_keys($calculado->conceptos),
                $calculado->conceptos,
            )),
        ]);
    }

    private function parte(DOMDocument $dom, int $i, ConceptoCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $concepto = $calculado->concepto;
        $ordenCompra = $datos->valorConcepto($i, 'ordenCompra');

        return $this->nodo($dom, 'part', [
            'numero' => $datos->valorConcepto($i, 'numero'),
            'cantidad' => $this->decimales($concepto->cantidad, 6),
            'descripcion' => $concepto->descripcion,
            'unidadDeMedida' => $datos->valorConcepto($i, 'unidadDeMedida') ?? $this->unidad($concepto),
            'precioUnitario' => $this->decimales($concepto->valorUnitario, 6),
            'montoDeLinea' => $this->decimales($calculado->importe, 6),
        ], [
            $ordenCompra === null ? null : $this->nodo($dom, 'references', [
                'ordenCompra' => $ordenCompra,
                'Requisicion' => $datos->valorConcepto($i, 'requisicion'),
                'ReferenciaProveedor' => $datos->valorConcepto($i, 'referenciaProveedor'),
            ]),
            // Un solo nodo: el XSD admite uno por parte, de hasta 200 caracteres (lo revisa RuleAddenda).
            ...array_slice($this->notas($dom, 'nota', $datos->valorConcepto($i, 'nota'), 200), 0, 1),
        ]);
    }
}
