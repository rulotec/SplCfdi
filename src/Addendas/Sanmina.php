<?php

declare(strict_types=1);

namespace SplCfdi\Addendas;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Addendas\{CampoAddenda, DatosAddenda, NivelCampo};
use SplCfdi\Domain\Models\ComprobanteCalculado;

/** Sanmina-SCI: un solo nodo AP con atributos de la factura, sin detalle por concepto. */
final class Sanmina extends AddendaBase
{
    public function clave(): string
    {
        return 'SANMINA';
    }

    public function nombre(): string
    {
        return 'Sanmina-SCI';
    }

    public function namespace(): string
    {
        return 'https://webapps2.sanmina-sci.com/partners-access';
    }

    public function campos(): array
    {
        return [
            new CampoAddenda('correoProveedor', 'Correo del proveedor', NivelCampo::Cliente, requerido: true, longitudMaxima: 100),
            new CampoAddenda('codigoCliente', 'Código de cliente', NivelCampo::Cliente, longitudMaxima: 100,
                ayuda: 'asignado por Sanmina-SCI'),
            new CampoAddenda('ordenCompra', 'Orden de compra', NivelCampo::Comprobante, longitudMaxima: 100),
        ];
    }

    public function crear(DOMDocument $dom, ComprobanteCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $c = $calculado->comprobante;
        $numeroInterno = $this->recortar(($c->serie ?? '') . ($c->folio ?? ''), 100);

        return $this->nodo($dom, 'AP', [
            'PONumber' => $datos->valor('ordenCompra'),
            'email' => $datos->valor('correoProveedor'),
            'LegalEntityName' => $this->recortar($c->receptor->nombre, 100),
            'CustomerCode' => $datos->valor('codigoCliente'),
            'Currency' => $c->moneda,
            'ExchangeRate' => $this->decimales($c->tipoCambio ?? '1', 4),
            'InternalInvoiceNumber' => $numeroInterno === '' ? null : $numeroInterno,
        ]);
    }
}
