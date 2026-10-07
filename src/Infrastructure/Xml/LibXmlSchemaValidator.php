<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Xml;

use DOMDocument;
use SplCfdi\Domain\Contracts\XmlSchemaValidator;
use SplCfdi\Domain\Exceptions\XmlSchemaValidationException;

final class LibXmlSchemaValidator implements XmlSchemaValidator
{
    private readonly string $xsdPath;

    public function __construct(?string $xsdPath = null)
    {
        $this->xsdPath = $xsdPath ?? __DIR__ . '/../../../resources/xsd/cfdv40.xsd';
    }

    public function validate(string $xml): void
    {
        if (!is_file($this->xsdPath)) {
            // Problema de configuración, no de datos: no es una validación fallida.
            throw new \RuntimeException("No se encontró el esquema XSD: {$this->xsdPath}");
        }

        if (trim($xml) === '') {
            throw new XmlSchemaValidationException(['El XML está vacío.']);
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();

            if (!$dom->loadXML($xml)) {
                throw new XmlSchemaValidationException($this->collectErrors());
            }

            if (!$dom->schemaValidate($this->xsdPath)) {
                throw new XmlSchemaValidationException($this->collectErrors());
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return string[] */
    private function collectErrors(): array
    {
        $errors = [];
        foreach (libxml_get_errors() as $e) {
            $errors[] = sprintf('línea %d: %s', $e->line, trim($e->message));
        }

        return $errors !== [] ? $errors : ['Error de validación desconocido.'];
    }
}
