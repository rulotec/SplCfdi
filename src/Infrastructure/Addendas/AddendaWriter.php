<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Addendas;

use DOMDocument;
use SplCfdi\Domain\Addendas\AddendaRegistry;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;

/**
 * Pone la addenda del comprobante en un XML de CFDI: crea cfdi:Addenda como último nodo de
 * cfdi:Comprobante, o reemplaza la que hubiera. Sirve para el XML recién generado y para uno ya
 * timbrado: la Addenda no forma parte de la cadena original, así que no afecta el sello ni el timbre.
 */
final class AddendaWriter
{
    public function __construct(private readonly AddendaRegistry $addendas) {}

    /**
     * Sin DatosAddenda, o con una clave que no existe (la reporta RuleAddenda), devuelve el XML intacto.
     *
     * @param bool $formatear true reindenta todo el documento (XML recién generado); false lo deja como venía
     *
     * @throws \InvalidArgumentException si el XML no es un cfdi:Comprobante bien formado
     */
    public function write(string $xml, ComprobanteCalculado $calculado, bool $formatear = false): string
    {
        $datos = $calculado->comprobante->addenda;
        $addenda = $datos === null ? null : $this->addendas->get($datos->clave);
        if ($datos === null || $addenda === null) {
            return $xml;
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = !$formatear;
        $root = $dom->loadXML($xml) ? $dom->documentElement : null;
        if ($root === null || $root->namespaceURI !== LibXmlSchemaValidator::NS_CFDI || $root->localName !== 'Comprobante') {
            throw new \InvalidArgumentException('El XML no es un CFDI 4.0 (cfdi:Comprobante).');
        }

        foreach (LibXmlSchemaValidator::addendas($dom) as $anterior) {
            $root->removeChild($anterior);
        }

        $nodo = $dom->createElementNS(LibXmlSchemaValidator::NS_CFDI, ($root->prefix ?: 'cfdi') . ':Addenda');
        $root->appendChild($nodo);
        $nodo->appendChild($addenda->crear($dom, $calculado, $datos->completadoCon($addenda->porDefecto($calculado->comprobante))));

        $dom->formatOutput = $formatear;

        return $dom->saveXML() ?: throw new \RuntimeException('No se pudo serializar el XML con la addenda.');
    }
}
