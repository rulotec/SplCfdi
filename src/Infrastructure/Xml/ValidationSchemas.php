<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Xml;

use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Infrastructure\Resources;

/**
 * Copia de resources/xsd para validar: idéntica, salvo que en catCFDI.xsd los catálogos grandes se
 * reducen a un patrón. Con la enumeración completa, libxml tarda ~0.5 s en cargar el esquema y ~40 s
 * en reportar un código postal inexistente (escribe las 95 000 claves en el mensaje). La existencia de
 * esas claves la revisa RuleCatalogos con los catálogos embebidos.
 *
 * La genera `php bin/cfdi-catalogs write`; `check` avisa si está desactualizada.
 */
final class ValidationSchemas
{
    public const DIR = 'xsd/validacion';

    /** Se copian tal cual, salvo catCFDI.xsd. */
    private const FILES = ['cfdv40.xsd', 'tdCFDI.xsd', 'catCFDI.xsd'];

    /** Catálogo => patrón que lo sustituye (la forma de sus claves). */
    public const PATTERNS = [
        CatalogName::CLAVE_PROD_SERV => '[0-9]{8}',
        CatalogName::CLAVE_UNIDAD => '[0-9A-Za-z]{2,3}',
        CatalogName::CODIGO_POSTAL => '[0-9]{5}',
    ];

    public static function schemaPath(): string
    {
        return Resources::path(self::DIR . '/cfdv40.xsd');
    }

    /**
     * Contenido de cada archivo de la copia.
     *
     * @return array<string, string> nombre de archivo => contenido
     */
    public static function render(string $sourceDir): array
    {
        $files = [];
        foreach (self::FILES as $file) {
            $path = rtrim($sourceDir, '/\\') . '/' . $file;
            $content = @file_get_contents($path);
            if ($content === false) {
                throw new \RuntimeException("No se encontró el XSD $path");
            }
            $files[$file] = $file === 'catCFDI.xsd' ? self::reduce($content) : $content;
        }

        return $files;
    }

    /** @return string[] archivos escritos */
    public static function write(string $sourceDir, string $targetDir): array
    {
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException("No se pudo crear $targetDir");
        }

        $written = [];
        foreach (self::render($sourceDir) as $file => $content) {
            file_put_contents("$targetDir/$file", $content);
            $written[] = "$targetDir/$file";
        }

        return $written;
    }

    /** @return string[] archivos de $targetDir que no coinciden con lo que generaría write() */
    public static function staleFiles(string $sourceDir, string $targetDir): array
    {
        $stale = [];
        foreach (self::render($sourceDir) as $file => $content) {
            if (@file_get_contents("$targetDir/$file") !== $content) {
                $stale[] = $file;
            }
        }

        return $stale;
    }

    private static function reduce(string $catalogXsd): string
    {
        foreach (self::PATTERNS as $name => $pattern) {
            $type = sprintf(
                "<xs:simpleType name=\"%s\">\n"
                . "\t\t<xs:annotation>\n"
                . "\t\t\t<xs:documentation>Copia de validación: la existencia de la clave la revisa RuleCatalogos.</xs:documentation>\n"
                . "\t\t</xs:annotation>\n"
                . "\t\t<xs:restriction base=\"xs:string\">\n"
                . "\t\t\t<xs:whiteSpace value=\"collapse\"/>\n"
                . "\t\t\t<xs:pattern value=\"%s\"/>\n"
                . "\t\t</xs:restriction>\n"
                . "\t</xs:simpleType>",
                $name,
                $pattern
            );

            // strpos y no una expresión regular: el tipo ocupa megabytes y agota el límite de PCRE.
            $start = strpos($catalogXsd, sprintf('<xs:simpleType name="%s">', $name));
            $end = $start === false ? false : strpos($catalogXsd, '</xs:simpleType>', $start);
            if ($start === false || $end === false) {
                throw new \RuntimeException("catCFDI.xsd no define el tipo $name: revisa ValidationSchemas::PATTERNS.");
            }

            $catalogXsd = substr_replace($catalogXsd, $type, $start, $end + strlen('</xs:simpleType>') - $start);
        }

        return $catalogXsd;
    }
}
