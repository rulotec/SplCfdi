<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Xml;

use DOMDocument;
use SplCfdi\Domain\Rules\Violation;

/** Valida un documento ya cargado contra un XSD y traduce los errores; lo comparten el CFDI y las addendas. */
final class XsdValidation
{
    /** @return Violation[] */
    public static function validate(DOMDocument $documento, string $xsdPath, XsdErrorTranslator $translator): array
    {
        if (!is_file($xsdPath)) {
            // Problema de instalación, no de datos: no es una validación fallida.
            throw new \RuntimeException("No se encontró el esquema XSD: $xsdPath");
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = self::unElementoPorLinea($documento);
            if ($dom->schemaValidate($xsdPath)) {
                return [];
            }

            return $translator->translate(libxml_get_errors(), $dom);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** Para que la línea de cada error identifique su elemento. */
    private static function unElementoPorLinea(DOMDocument $documento): DOMDocument
    {
        $copia = new DOMDocument();
        $copia->preserveWhiteSpace = false;
        $copia->loadXML((string) $documento->saveXML());
        $copia->formatOutput = true;

        $dom = new DOMDocument();
        $dom->loadXML((string) $copia->saveXML());

        return $dom;
    }
}
