<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Cfdi;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Contracts\CfdiXmlGenerator;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\ConceptoCalculado;
use SplCfdi\Domain\Models\Emisor;
use SplCfdi\Domain\Models\ImpuestoRetenidoCalculado;
use SplCfdi\Domain\Models\ImpuestoRetenidoResumen;
use SplCfdi\Domain\Models\ImpuestosComprobante;
use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;
use SplCfdi\Domain\Models\Receptor;

final class SimpleCfdiXmlGenerator implements CfdiXmlGenerator
{
    private const NS_CFDI = 'http://www.sat.gob.mx/cfd/4';
    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const NS_XMLNS = 'http://www.w3.org/2000/xmlns/';
    private const XSD_URL = 'http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd';

    private DOMDocument $dom;

    public function generate(ComprobanteCalculado $calculado): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        $this->dom->appendChild($this->crearComprobante($calculado));

        return $this->dom->saveXML();
    }

    private function crearComprobante(ComprobanteCalculado $calculado): DOMElement
    {
        $c = $calculado->comprobante;
        $importes = $calculado->importes;

        $raiz = $this->nodo('Comprobante');
        $raiz->setAttributeNS(self::NS_XMLNS, 'xmlns:cfdi', self::NS_CFDI);
        $raiz->setAttributeNS(self::NS_XMLNS, 'xmlns:xsi', self::NS_XSI);
        $raiz->setAttributeNS(self::NS_XSI, 'xsi:schemaLocation', self::NS_CFDI . ' ' . self::XSD_URL);

        $this->completar($raiz, [
            'Version' => $c->version,
            'Serie' => $c->serie,
            'Folio' => $c->folio,
            'Fecha' => $c->fecha,
            'FormaPago' => $c->formaPago,
            'SubTotal' => $importes->subTotal,
            'Descuento' => $importes->descuento,
            'Moneda' => $c->moneda->codigo,
            'Total' => $importes->total,
            'TipoDeComprobante' => $c->tipoDeComprobante,
            'Exportacion' => $c->exportacion,
            'MetodoPago' => $c->metodoPago,
            'LugarExpedicion' => $c->lugarExpedicion,
        ], [
            $this->crearEmisor($c->emisor),
            $this->crearReceptor($c->receptor),
            $this->crearConceptos($calculado->conceptos),
            $this->crearImpuestos($calculado->impuestos),
        ]);

        return $raiz;
    }

    private function crearEmisor(Emisor $emisor): DOMElement
    {
        return $this->nodo('Emisor', [
            'Rfc' => $emisor->rfc,
            'Nombre' => $emisor->nombre,
            'RegimenFiscal' => $emisor->regimenFiscal,
        ]);
    }

    private function crearReceptor(Receptor $receptor): DOMElement
    {
        return $this->nodo('Receptor', [
            'Rfc' => $receptor->rfc,
            'Nombre' => $receptor->nombre,
            'DomicilioFiscalReceptor' => $receptor->domicilioFiscalReceptor,
            'RegimenFiscalReceptor' => $receptor->regimenFiscalReceptor,
            'UsoCFDI' => $receptor->usoCFDI,
        ]);
    }

    /** @param ConceptoCalculado[] $conceptos */
    private function crearConceptos(array $conceptos): DOMElement
    {
        return $this->nodo('Conceptos', [], array_map(
            fn (ConceptoCalculado $calculado) => $this->nodo('Concepto', [
                'ClaveProdServ' => $calculado->concepto->claveProdServ,
                'Cantidad' => $calculado->concepto->cantidad,
                'ClaveUnidad' => $calculado->concepto->claveUnidad,
                'Unidad' => $calculado->concepto->unidad,
                'Descripcion' => $calculado->concepto->descripcion,
                'ValorUnitario' => $calculado->concepto->valorUnitario,
                'Importe' => $calculado->importe,
                'Descuento' => $calculado->descuento,
                'ObjetoImp' => $calculado->concepto->objetoImp,
            ], [$this->crearImpuestosConcepto($calculado)]),
            $conceptos
        ));
    }

    private function crearImpuestosConcepto(ConceptoCalculado $calculado): ?DOMElement
    {
        // A nivel concepto el esquema pide Traslados y después Retenciones
        $bloques = array_filter([
            $this->bloqueTraslados($calculado->traslados),
            $this->bloqueRetenciones($calculado->retenciones),
        ]);

        return $bloques === [] ? null : $this->nodo('Impuestos', [], $bloques);
    }

    private function crearImpuestos(ImpuestosComprobante $impuestos): ?DOMElement
    {
        // A nivel comprobante el orden es el inverso: Retenciones y después Traslados
        $bloques = array_filter([
            $this->bloqueRetencionesResumen($impuestos->retenciones),
            $this->bloqueTraslados($impuestos->traslados),
        ]);

        if ($bloques === []) {
            return null;
        }

        return $this->nodo('Impuestos', [
            'TotalImpuestosRetenidos' => $impuestos->totalImpuestosRetenidos,
            'TotalImpuestosTrasladados' => $impuestos->totalImpuestosTrasladados,
        ], $bloques);
    }

    /** @param ImpuestoTrasladadoCalculado[] $traslados */
    private function bloqueTraslados(array $traslados): ?DOMElement
    {
        return $this->bloque('Traslados', 'Traslado', $traslados, fn (ImpuestoTrasladadoCalculado $t) => [
            'Base' => $t->base,
            'Impuesto' => $t->impuesto,
            'TipoFactor' => $t->tipoFactor->value,
            'TasaOCuota' => $t->tasaOCuota,   // null si es Exento
            'Importe' => $t->importe,         // null si es Exento
        ]);
    }

    /** @param ImpuestoRetenidoCalculado[] $retenciones */
    private function bloqueRetenciones(array $retenciones): ?DOMElement
    {
        return $this->bloque('Retenciones', 'Retencion', $retenciones, fn (ImpuestoRetenidoCalculado $r) => [
            'Base' => $r->base,
            'Impuesto' => $r->impuesto,
            'TipoFactor' => $r->tipoFactor->value,
            'TasaOCuota' => $r->tasaOCuota,
            'Importe' => $r->importe,
        ]);
    }

    /** @param ImpuestoRetenidoResumen[] $retenciones */
    private function bloqueRetencionesResumen(array $retenciones): ?DOMElement
    {
        return $this->bloque('Retenciones', 'Retencion', $retenciones, fn (ImpuestoRetenidoResumen $r) => [
            'Impuesto' => $r->impuesto,
            'Importe' => $r->importe,
        ]);
    }

    /**
     * Contenedor con un hijo por elemento; null si no hay elementos.
     *
     * @param array<int, mixed> $items
     * @param callable(mixed): array<string, ?string> $atributos
     */
    private function bloque(string $contenedor, string $hijo, array $items, callable $atributos): ?DOMElement
    {
        if ($items === []) {
            return null;
        }

        return $this->nodo(
            $contenedor,
            [],
            array_map(fn ($item) => $this->nodo($hijo, $atributos($item)), $items)
        );
    }

    /**
     * @param array<string, ?string> $atributos los null se omiten
     * @param array<?DOMElement> $hijos los null se omiten
     */
    private function nodo(string $nombre, array $atributos = [], array $hijos = []): DOMElement
    {
        $nodo = $this->dom->createElementNS(self::NS_CFDI, 'cfdi:' . $nombre);
        $this->completar($nodo, $atributos, $hijos);

        return $nodo;
    }

    /**
     * @param array<string, ?string> $atributos
     * @param array<?DOMElement> $hijos
     */
    private function completar(DOMElement $nodo, array $atributos, array $hijos = []): void
    {
        foreach ($atributos as $nombre => $valor) {
            if ($valor !== null) {
                $nodo->setAttribute($nombre, $valor);
            }
        }

        foreach ($hijos as $hijo) {
            if ($hijo !== null) {
                $nodo->appendChild($hijo);
            }
        }
    }
}
