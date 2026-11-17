<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Xml;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Contracts\XmlSchemaValidator;
use SplCfdi\Domain\Rules\Violation;

/**
 * Valida contra cfdv40.xsd (por defecto la copia de validación, ver ValidationSchemas) y devuelve los
 * errores traducidos; nunca lanza por datos inválidos.
 *
 * Mientras no haya sellado, a un XML sin Sello/NoCertificado/Certificado se le ponen valores ficticios:
 * el XSD los exige, pero no dependen de los datos del host.
 *
 * La Addenda y el Complemento se quitan antes de validar: cfdv40.xsd los declara como xs:any estricto, que
 * exige conocer el esquema de su contenido. Cada addenda se valida aparte contra el suyo
 * (AddendaSchemaValidator); los complementos (TimbreFiscalDigital, que agrega el PAC, y los demás) no se
 * validan, porque no se incluyen sus XSD.
 */
final class LibXmlSchemaValidator implements XmlSchemaValidator
{
    /** Valores con la forma que exige el XSD; un CFDI con ellos no es válido ante el SAT. */
    public const SELLO_FICTICIO = [
        'Sello' => 'SELLO-FICTICIO',
        'NoCertificado' => '00000000000000000000',
        'Certificado' => 'CERTIFICADO-FICTICIO',
    ];

    public const NS_CFDI = 'http://www.sat.gob.mx/cfd/4';

    private readonly string $xsdPath;

    public function __construct(
        ?string $xsdPath = null,
        private readonly XsdErrorTranslator $translator = new XsdErrorTranslator(),
    ) {
        $this->xsdPath = $xsdPath ?? ValidationSchemas::schemaPath();
    }

    public function validate(string $xml): array
    {
        if (!is_file($this->xsdPath)) {
            // Problema de instalación, no de datos: no es una validación fallida.
            throw new \RuntimeException("No se encontró el esquema XSD: {$this->xsdPath}. Ejecuta: php bin/cfdi-catalogs write");
        }

        if (trim($xml) === '') {
            return [new Violation('xml-vacio', 'El XML está vacío.')];
        }

        $dom = self::cargar($xml);
        if (is_string($dom)) {
            return [new Violation('xml-mal-formado', $dom)];
        }

        $root = $dom->documentElement;
        if ($root !== null) {
            foreach (self::SELLO_FICTICIO as $atributo => $valor) {
                if (!$root->hasAttribute($atributo)) {
                    $root->setAttribute($atributo, $valor);
                }
            }
            foreach ([...self::addendas($dom), ...self::hijos($dom, 'Complemento')] as $nodo) {
                $root->removeChild($nodo);
            }
        }

        return XsdValidation::validate($dom, $this->xsdPath, $this->translator);
    }

    /**
     * Los nodos cfdi:Addenda del comprobante.
     *
     * @return DOMElement[]
     */
    public static function addendas(DOMDocument $dom): array
    {
        return self::hijos($dom, 'Addenda');
    }

    /**
     * Los nodos cfdi:$nombre hijos directos del comprobante.
     *
     * @return DOMElement[]
     */
    private static function hijos(DOMDocument $dom, string $nombre): array
    {
        $hijos = [];
        foreach ($dom->documentElement->childNodes ?? [] as $nodo) {
            if ($nodo instanceof DOMElement && $nodo->namespaceURI === self::NS_CFDI && $nodo->localName === $nombre) {
                $hijos[] = $nodo;
            }
        }

        return $hijos;
    }

    /** @return DOMDocument|string el documento, o el mensaje de por qué no está bien formado */
    public static function cargar(string $xml): DOMDocument|string
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            if ($dom->loadXML($xml)) {
                return $dom;
            }
            $error = libxml_get_errors()[0] ?? null;

            return $error === null
                ? 'El XML no está bien formado.'
                : sprintf('El XML no está bien formado (línea %d): %s', $error->line, trim($error->message));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
