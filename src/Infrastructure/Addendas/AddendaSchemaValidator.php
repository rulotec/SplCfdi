<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Addendas;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Addendas\AddendaRegistry;
use SplCfdi\Domain\Contracts\XmlSchemaValidator;
use SplCfdi\Infrastructure\Xml\{LibXmlSchemaValidator, XsdErrorTranslator, XsdValidation};

/**
 * Valida cada addenda del XML contra su propio XSD, como documento aparte. Reconoce la addenda por el
 * namespace de su nodo; las que no están en el registro (o no tienen XSD) no se revisan.
 *
 * Solo revisa addendas: el resto del CFDI lo valida LibXmlSchemaValidator, que también reporta un XML
 * mal formado (aquí no se repite).
 */
final class AddendaSchemaValidator implements XmlSchemaValidator
{
    public function __construct(private readonly AddendaRegistry $addendas) {}

    public function validate(string $xml): array
    {
        $dom = LibXmlSchemaValidator::cargar($xml);
        if (is_string($dom)) {
            return [];
        }

        $violaciones = [];
        foreach (LibXmlSchemaValidator::addendas($dom) as $contenedor) {
            foreach ($contenedor->childNodes as $nodo) {
                $addenda = $nodo instanceof DOMElement ? $this->addendas->porNamespace((string) $nodo->namespaceURI) : null;
                $esquema = $addenda?->esquema();
                if ($addenda === null || $esquema === null) {
                    continue;
                }

                $fragmento = new DOMDocument();
                $fragmento->appendChild($fragmento->importNode($nodo, true));
                $translator = XsdErrorTranslator::fragmento("Addenda {$addenda->nombre()}", 'Addenda');
                $violaciones = [...$violaciones, ...XsdValidation::validate($fragmento, $esquema, $translator)];
            }
        }

        return $violaciones;
    }
}
