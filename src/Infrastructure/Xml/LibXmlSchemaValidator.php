<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Xml;

use DOMDocument;
use SplCfdi\Domain\Contracts\XmlSchemaValidator;
use SplCfdi\Domain\Rules\Violation;

/**
 * Valida contra cfdv40.xsd (por defecto la copia de validación, ver ValidationSchemas) y devuelve los
 * errores traducidos; nunca lanza por datos inválidos.
 *
 * Mientras no haya sellado, a un XML sin Sello/NoCertificado/Certificado se le ponen valores ficticios:
 * el XSD los exige, pero no dependen de los datos del host. El TimbreFiscalDigital no hace falta: va en
 * Complemento, que es opcional, y lo agrega el PAC.
 */
final class LibXmlSchemaValidator implements XmlSchemaValidator
{
    /** Valores con la forma que exige el XSD; un CFDI con ellos no es válido ante el SAT. */
    public const SELLO_FICTICIO = [
        'Sello' => 'SELLO-FICTICIO',
        'NoCertificado' => '00000000000000000000',
        'Certificado' => 'CERTIFICADO-FICTICIO',
    ];

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

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = $this->cargar($xml);
            if ($dom === null) {
                $error = libxml_get_errors()[0] ?? null;

                return [new Violation('xml-mal-formado', $error === null
                    ? 'El XML no está bien formado.'
                    : sprintf('El XML no está bien formado (línea %d): %s', $error->line, trim($error->message)))];
            }

            libxml_clear_errors();
            if ($dom->schemaValidate($this->xsdPath)) {
                return [];
            }

            return $this->translator->translate(libxml_get_errors(), $dom);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** Un elemento por línea, para que la línea de cada error identifique su elemento. */
    private function cargar(string $xml): ?DOMDocument
    {
        $original = new DOMDocument();
        $original->preserveWhiteSpace = false;
        if (!$original->loadXML($xml)) {
            return null;
        }

        $root = $original->documentElement;
        if ($root !== null) {
            foreach (self::SELLO_FICTICIO as $atributo => $valor) {
                if (!$root->hasAttribute($atributo)) {
                    $root->setAttribute($atributo, $valor);
                }
            }
        }

        $original->formatOutput = true;
        $dom = new DOMDocument();

        return $dom->loadXML((string) $original->saveXML()) ? $dom : null;
    }
}
