<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

use DOMDocument;
use DOMXPath;

/** Lee de catCFDI.xsd los valores de los tipos simples enumerados (xs:enumeration). */
final class CatalogXsdReader
{
    private const NS_XS = 'http://www.w3.org/2001/XMLSchema';

    /**
     * Solo extrae los tipos pedidos: catCFDI.xsd trae catálogos enormes (ClaveProdServ, CodigoPostal)
     * que no hace falta recorrer.
     *
     * @param string[] $names
     *
     * @return array<string, string[]> únicamente los tipos encontrados con al menos un valor
     */
    public function read(string $xsdPath, array $names): array
    {
        if (!is_file($xsdPath)) {
            throw new \RuntimeException("No se encontró el XSD de catálogos: $xsdPath");
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            if (!$dom->load($xsdPath, LIBXML_PARSEHUGE)) {
                throw new \RuntimeException("No se pudo leer el XSD de catálogos: $xsdPath");
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('xs', self::NS_XS);

        $found = [];
        foreach ($names as $name) {
            $nodes = $xpath->query(sprintf(
                "/xs:schema/xs:simpleType[@name='%s']/xs:restriction/xs:enumeration/@value",
                $name
            ));

            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $attribute) {
                $found[$name][] = (string) $attribute->nodeValue;
            }
        }

        return $found;
    }
}
