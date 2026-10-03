<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Cfdi;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Models\ConceptoCalculado;
use SplCfdi\Domain\Contracts\CfdiXmlGenerator;
use SplCfdi\Domain\Models\ComprobanteCalculado;

final class SimpleCfdiXmlGenerator implements CfdiXmlGenerator
{
    private const NAMESPACE_CFDI = 'http://www.sat.gob.mx/cfd/4';
    private const NAMESPACE_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const XSD_URL = 'http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd';

    private DOMDocument $domDocumentCfdi;
    private DOMElement $xmlComprobante;
    private ComprobanteCalculado $comprobanteCalculado;
    private Comprobante $comprobante;

    public function generate(ComprobanteCalculado $comprobanteCalculado): string {

        $this->comprobanteCalculado = $comprobanteCalculado;
        $this->comprobante = $comprobanteCalculado->comprobante;

        if (
            $this->comprobanteCalculado->importes === null ||
            $this->comprobanteCalculado->impuestos === null
        ) {
            throw new \LogicException(
                'El comprobante debe tener importes e impuestos antes de generar el XML.'
            );
        }

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
        $this->domDocumentCfdi = new DOMDocument(
            '1.0',
            'UTF-8'
        );

        $this->domDocumentCfdi->formatOutput = true;

        $this->xmlComprobante = $this->domDocumentCfdi->createElementNS(
            self::NAMESPACE_CFDI,
            'cfdi:Comprobante'
        );

        $this->domDocumentCfdi->appendChild(
            $this->xmlComprobante
        );
    }

    private function agregarNamespaces(): void
    {
        $this->xmlComprobante->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cfdi', self::NAMESPACE_CFDI);
        $this->xmlComprobante->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NAMESPACE_XSI);
        $this->xmlComprobante->setAttributeNS(
            self::NAMESPACE_XSI,
            'xsi:schemaLocation',
            self::NAMESPACE_CFDI
            . ' '
            . self::XSD_URL
        );
    }

    private function agregarAtributosComprobante(): void
    {
        $this->xmlComprobante->setAttribute('Version', $this->comprobante->version);
        $this->xmlComprobante->setAttribute('Fecha', $this->comprobante->fecha);
        $this->xmlComprobante->setAttribute('SubTotal', $this->comprobanteCalculado->importes->subTotal);
        $this->xmlComprobante->setAttribute('Total', $this->comprobanteCalculado->importes->total);
        $this->xmlComprobante->setAttribute('Moneda', $this->comprobante->moneda->codigo);
        $this->xmlComprobante->setAttribute('TipoDeComprobante', $this->comprobante->tipoDeComprobante);
        $this->xmlComprobante->setAttribute('Exportacion', $this->comprobante->exportacion);
        $this->xmlComprobante->setAttribute('LugarExpedicion', $this->comprobante->lugarExpedicion);

        $this->agregarAtributoOpcional($this->xmlComprobante, 'Serie', $this->comprobante->serie);
        $this->agregarAtributoOpcional($this->xmlComprobante, 'Folio',  $this->comprobante->folio);
        $this->agregarAtributoOpcional($this->xmlComprobante, 'MetodoPago', $this->comprobante->metodoPago);
        $this->agregarAtributoOpcional($this->xmlComprobante,  'FormaPago', $this->comprobante->formaPago);
    }

    private function agregarAtributoOpcional(DOMElement $elemento, string $nombre, ?string $valor): void
    {
        if ($valor !== null) {
            $elemento->setAttribute(
                $nombre,
                $valor
            );
        }
    }

    private function agregarEmisor(): void
    {
        $emisor = $this->comprobante->emisor;

        $elementoEmisor = $this->domDocumentCfdi->createElementNS(
            self::NAMESPACE_CFDI,
            'cfdi:Emisor'
        );

        $elementoEmisor->setAttribute('Rfc', $emisor->rfc);
        $elementoEmisor->setAttribute('Nombre', $emisor->nombre);
        $elementoEmisor->setAttribute('RegimenFiscal', $emisor->regimenFiscal);

        $this->xmlComprobante->appendChild($elementoEmisor);
    }

    private function agregarReceptor(): void
    {
        $receptor = $this->comprobante->receptor;

        $elementoReceptor = $this->domDocumentCfdi->createElementNS(
            self::NAMESPACE_CFDI,
            'cfdi:Receptor'
        );

        $elementoReceptor->setAttribute('Rfc', $receptor->rfc);
        $elementoReceptor->setAttribute('Nombre', $receptor->nombre);
        $elementoReceptor->setAttribute('DomicilioFiscalReceptor', $receptor->domicilioFiscalReceptor);
        $elementoReceptor->setAttribute('RegimenFiscalReceptor', $receptor->regimenFiscalReceptor);
        $elementoReceptor->setAttribute('UsoCFDI', $receptor->usoCFDI);

        $this->xmlComprobante->appendChild($elementoReceptor);
    }

    private function agregarConceptos(): void
    {
        $elementoConceptos = $this->domDocumentCfdi->createElementNS(
            self::NAMESPACE_CFDI,
            'cfdi:Conceptos'
        );

        /**
         * @var ConceptoCalculado $conceptoCalculado
         */
        foreach ($this->comprobanteCalculado->conceptos as $conceptoCalculado) {
            $elementoConcepto = $this->domDocumentCfdi->createElementNS(
                self::NAMESPACE_CFDI,
                'cfdi:Concepto'
            );

            $this->agregarAtributosConcepto($elementoConcepto, $conceptoCalculado->concepto);

            $impuestos = $conceptoCalculado->traslados;
            if ($impuestos) {
                $this->agregarImpuestosConcepto($elementoConcepto, $impuestos);
            }

            $elementoConceptos->appendChild($elementoConcepto);

        }

        $this->xmlComprobante->appendChild($elementoConceptos);
    }

    private function agregarAtributosConcepto(\DOMElement $elementoConcepto, Concepto $concepto)
    {
        $elementoConcepto->setAttribute('ClaveProdServ', $concepto->claveProdServ);
        $elementoConcepto->setAttribute('Cantidad' ,$concepto->cantidad);
        $elementoConcepto->setAttribute('ClaveUnidad', $concepto->claveUnidad);
        $elementoConcepto->setAttribute('Unidad', $concepto->unidad);
        $elementoConcepto->setAttribute('Descripcion', $concepto->descripcion);
        $elementoConcepto->setAttribute('ValorUnitario', $concepto->valorUnitario);
        $elementoConcepto->setAttribute('Importe', $concepto->importe);
        $elementoConcepto->setAttribute('ObjetoImp', $concepto->objetoImp);
    }

    private function agregarImpuestosConcepto(DOMElement $elementoConcepto, array $impuestos): void
    {
        $impuestosXml = $this->domDocumentCfdi->createElementNS(self::NAMESPACE_CFDI, 'cfdi:Impuestos');
        $trasladosXml = $this->domDocumentCfdi->createElementNS(self::NAMESPACE_CFDI, 'cfdi:Traslados');

        foreach ($impuestos as $impuesto) {
            $trasladoXml = $this->domDocumentCfdi->createElementNS(
                self::NAMESPACE_CFDI,
                'cfdi:Traslado'
            );

            $trasladoXml->setAttribute('Base', $impuesto->base);
            $trasladoXml->setAttribute('Impuesto', $impuesto->impuesto);
            $trasladoXml->setAttribute('TipoFactor', $impuesto->tipoFactor);
            $trasladoXml->setAttribute('TasaOCuota', $impuesto->tasaOCuota);
            $trasladoXml->setAttribute('Importe', $impuesto->importe);

            $trasladosXml->appendChild($trasladoXml);
        }

        $impuestosXml->appendChild($trasladosXml);
        $elementoConcepto->appendChild($impuestosXml);
    }

    private function agregarImpuestos(): void
    {
        $impuestos = $this->comprobanteCalculado->impuestos;

        if (
            $impuestos->totalImpuestosTrasladados === null &&
            $impuestos->totalImpuestosRetenidos === null
        ) {
            return;
        }

        $impuestosXml = $this->domDocumentCfdi->createElement('cfdi:Impuestos');

        $this->agregarAtributoOpcional(
            $impuestosXml,
            'TotalImpuestosRetenidos',
            $impuestos->totalImpuestosRetenidos
        );

        $this->agregarAtributoOpcional(
            $impuestosXml,
            'TotalImpuestosTrasladados',
            $impuestos->totalImpuestosTrasladados
        );

        if (count($impuestos->traslados) > 0) {
            $trasladosXml = $this->domDocumentCfdi->createElement('cfdi:Traslados');

            foreach ($impuestos->traslados as $impuesto) {
                $trasladoXml = $this->domDocumentCfdi->createElement('cfdi:Traslado');

                $trasladoXml->setAttribute('Base', $impuesto->base);
                $trasladoXml->setAttribute('Impuesto', $impuesto->impuesto);
                $trasladoXml->setAttribute('TipoFactor', $impuesto->tipoFactor);
                $trasladoXml->setAttribute('TasaOCuota', $impuesto->tasaOCuota);
                $trasladoXml->setAttribute('Importe', $impuesto->importe);

                $trasladosXml->appendChild($trasladoXml);
            }

            $impuestosXml->appendChild($trasladosXml);
        }

        $this->xmlComprobante->appendChild($impuestosXml);
    }
}
