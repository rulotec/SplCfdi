<?php

declare(strict_types = 1);

namespace SplCfdi\Infrastructure\Cfdi;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Contracts\CfdiXmlGenerator;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\ConceptoCalculado;
use SplCfdi\Domain\Models\ImpuestoRetenidoCalculado;
use SplCfdi\Domain\Models\ImpuestoRetenidoResumen;
use SplCfdi\Domain\Models\ImpuestoTrasladadoCalculado;

final class SimpleCfdiXmlGenerator implements CfdiXmlGenerator
{

    private const NAMESPACE_CFDI = 'http://www.sat.gob.mx/cfd/4';

    private const NAMESPACE_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    private const XSD_URL = 'http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd';

    private DOMDocument $domDocumentCfdi;

    private DOMElement $xmlComprobante;

    private ComprobanteCalculado $comprobanteCalculado;

    private Comprobante $comprobante;

    public function generate(ComprobanteCalculado $comprobanteCalculado): string
    {
        $this->comprobanteCalculado = $comprobanteCalculado;
        $this->comprobante = $comprobanteCalculado->comprobante;

        $this->createDomDocument();
        $this->agregarNamespaces();
        $this->agregarAtributosComprobante();
        $this->agregarEmisor();
        $this->agregarReceptor();
        $this->agregarConceptos();
        $this->agregarImpuestos();

        return $this->domDocumentCfdi->saveXML();
    }

    private function createDomDocument(): void
    {
        $this->domDocumentCfdi = new DOMDocument('1.0', 'UTF-8');
        $this->domDocumentCfdi->formatOutput = true;

        $this->xmlComprobante = $this->crearElemento('Comprobante');
        $this->domDocumentCfdi->appendChild($this->xmlComprobante);
    }

    private function crearElemento(string $nombre): DOMElement
    {
        return $this->domDocumentCfdi->createElementNS(self::NAMESPACE_CFDI, 'cfdi:' . $nombre);
    }

    private function agregarNamespaces(): void
    {
        $this->xmlComprobante->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cfdi', self::NAMESPACE_CFDI);
        $this->xmlComprobante->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NAMESPACE_XSI);
        $this->xmlComprobante->setAttributeNS(self::NAMESPACE_XSI, 'xsi:schemaLocation', self::NAMESPACE_CFDI . ' ' . self::XSD_URL);
    }

    private function agregarAtributosComprobante(): void
    {
        $c = $this->comprobante;
        $calc = $this->comprobanteCalculado;

        $this->xmlComprobante->setAttribute('Version', $c->version);
        $this->xmlComprobante->setAttribute('Fecha', $c->fecha);
        $this->xmlComprobante->setAttribute('SubTotal', $calc->importes->subTotal);
        $this->xmlComprobante->setAttribute('Total', $calc->importes->total);
        $this->xmlComprobante->setAttribute('Moneda', $c->moneda->codigo);
        $this->xmlComprobante->setAttribute('TipoDeComprobante', $c->tipoDeComprobante);
        $this->xmlComprobante->setAttribute('Exportacion', $c->exportacion);
        $this->xmlComprobante->setAttribute('LugarExpedicion', $c->lugarExpedicion);

        $this->agregarAtributoOpcional($this->xmlComprobante, 'Serie', $c->serie);
        $this->agregarAtributoOpcional($this->xmlComprobante, 'Folio', $c->folio);
        $this->agregarAtributoOpcional($this->xmlComprobante, 'MetodoPago', $c->metodoPago);
        $this->agregarAtributoOpcional($this->xmlComprobante, 'FormaPago', $c->formaPago);
        $this->agregarAtributoOpcional($this->xmlComprobante, 'Descuento', $calc->importes->descuento);
    }

    private function agregarAtributoOpcional(DOMElement $elemento, string $nombre, ?string $valor): void
    {
        if ($valor !== null) {
            $elemento->setAttribute($nombre, $valor);
        }
    }

    private function agregarEmisor(): void
    {
        $emisor = $this->comprobante->emisor;
        $elemento = $this->crearElemento('Emisor');

        $elemento->setAttribute('Rfc', $emisor->rfc);
        $elemento->setAttribute('Nombre', $emisor->nombre);
        $elemento->setAttribute('RegimenFiscal', $emisor->regimenFiscal);

        $this->xmlComprobante->appendChild($elemento);
    }

    private function agregarReceptor(): void
    {
        $receptor = $this->comprobante->receptor;
        $elemento = $this->crearElemento('Receptor');

        $elemento->setAttribute('Rfc', $receptor->rfc);
        $elemento->setAttribute('Nombre', $receptor->nombre);
        $elemento->setAttribute('DomicilioFiscalReceptor', $receptor->domicilioFiscalReceptor);
        $elemento->setAttribute('RegimenFiscalReceptor', $receptor->regimenFiscalReceptor);
        $elemento->setAttribute('UsoCFDI', $receptor->usoCFDI);

        $this->xmlComprobante->appendChild($elemento);
    }

    private function agregarConceptos(): void
    {
        $elementoConceptos = $this->crearElemento('Conceptos');

        /** @var ConceptoCalculado $conceptoCalculado */
        foreach ($this->comprobanteCalculado->conceptos as $conceptoCalculado) {
            $elementoConcepto = $this->crearElemento('Concepto');
            $this->agregarAtributosConcepto($elementoConcepto, $conceptoCalculado);

            if ($conceptoCalculado->traslados !== [] || $conceptoCalculado->retenciones !== []) {
                $impuestosConcepto = $this->crearElemento('Impuestos');

                // A nivel concepto el esquema pide Traslados y después Retenciones
                if ($conceptoCalculado->traslados !== []) {
                    $impuestosConcepto->appendChild($this->crearBloqueTraslados($conceptoCalculado->traslados));
                }
                if ($conceptoCalculado->retenciones !== []) {
                    $impuestosConcepto->appendChild($this->crearBloqueRetenciones($conceptoCalculado->retenciones));
                }

                $elementoConcepto->appendChild($impuestosConcepto);
            }

            $elementoConceptos->appendChild($elementoConcepto);
        }

        $this->xmlComprobante->appendChild($elementoConceptos);
    }


    private function agregarAtributosConcepto(DOMElement $elementoConcepto, ConceptoCalculado $calculado): void
    {
        $concepto = $calculado->concepto;

        $elementoConcepto->setAttribute('ClaveProdServ', $concepto->claveProdServ);
        $elementoConcepto->setAttribute('Cantidad', $concepto->cantidad);
        $elementoConcepto->setAttribute('ClaveUnidad', $concepto->claveUnidad);
        $elementoConcepto->setAttribute('Unidad', $concepto->unidad);
        $elementoConcepto->setAttribute('Descripcion', $concepto->descripcion);
        $elementoConcepto->setAttribute('ValorUnitario', $concepto->valorUnitario);
        $elementoConcepto->setAttribute('Importe', $calculado->importe);
        $this->agregarAtributoOpcional($elementoConcepto, 'Descuento', $calculado->descuento);
        $elementoConcepto->setAttribute('ObjetoImp', $concepto->objetoImp);
    }

    private function agregarImpuestos(): void
    {
        $impuestos = $this->comprobanteCalculado->impuestos;

        if ($impuestos->traslados === [] && $impuestos->retenciones === []) {
            return;
        }

        $impuestosXml = $this->crearElemento('Impuestos');

        $this->agregarAtributoOpcional($impuestosXml, 'TotalImpuestosRetenidos', $impuestos->totalImpuestosRetenidos);
        $this->agregarAtributoOpcional($impuestosXml, 'TotalImpuestosTrasladados', $impuestos->totalImpuestosTrasladados);

        // A nivel comprobante el orden es el inverso: Retenciones y después Traslados
        if ($impuestos->retenciones !== []) {
            $impuestosXml->appendChild($this->crearBloqueRetencionesResumen($impuestos->retenciones));
        }
        if ($impuestos->traslados !== []) {
            $impuestosXml->appendChild($this->crearBloqueTraslados($impuestos->traslados));
        }

        $this->xmlComprobante->appendChild($impuestosXml);
    }

    /**
     * Concepto lleva <Impuestos><Traslados>; el resumen lleva solo <Traslados>.
     * Este método devuelve <Traslados> y cada caller lo coloca donde corresponde.
     *
     * @param ImpuestoTrasladadoCalculado[] $traslados
     */
    private function crearBloqueTraslados(array $traslados): DOMElement
    {
        $trasladosXml = $this->crearElemento('Traslados');

        foreach ($traslados as $traslado) {
            $trasladosXml->appendChild($this->crearTraslado($traslado));
        }

        return $trasladosXml;
    }

    private function crearTraslado(ImpuestoTrasladadoCalculado $impuesto): DOMElement
    {
        $elemento = $this->crearElemento('Traslado');

        $elemento->setAttribute('Base', $impuesto->base);
        $elemento->setAttribute('Impuesto', $impuesto->impuesto);
        $elemento->setAttribute('TipoFactor', $impuesto->tipoFactor->value);
        // Exento: sin TasaOCuota ni Importe
        $this->agregarAtributoOpcional($elemento, 'TasaOCuota', $impuesto->tasaOCuota);
        $this->agregarAtributoOpcional($elemento, 'Importe', $impuesto->importe);

        return $elemento;
    }

    /** @param ImpuestoRetenidoCalculado[] $retenciones */
    private function crearBloqueRetenciones(array $retenciones): DOMElement
    {
        $bloque = $this->crearElemento('Retenciones');

        foreach ($retenciones as $retencion) {
            $elemento = $this->crearElemento('Retencion');
            $elemento->setAttribute('Base', $retencion->base);
            $elemento->setAttribute('Impuesto', $retencion->impuesto);
            $elemento->setAttribute('TipoFactor', $retencion->tipoFactor->value);
            $elemento->setAttribute('TasaOCuota', $retencion->tasaOCuota);
            $elemento->setAttribute('Importe', $retencion->importe);
            $bloque->appendChild($elemento);
        }

        return $bloque;
    }

    /** @param ImpuestoRetenidoResumen[] $retenciones */
    private function crearBloqueRetencionesResumen(array $retenciones): DOMElement
    {
        $bloque = $this->crearElemento('Retenciones');

        foreach ($retenciones as $retencion) {
            $elemento = $this->crearElemento('Retencion');
            $elemento->setAttribute('Impuesto', $retencion->impuesto);
            $elemento->setAttribute('Importe', $retencion->importe);
            $bloque->appendChild($elemento);
        }

        return $bloque;
    }
}
