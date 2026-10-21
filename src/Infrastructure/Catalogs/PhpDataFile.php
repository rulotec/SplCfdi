<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

/** Lee los archivos PHP de resources/catalogs, que devuelven arreglos de claves. */
final class PhpDataFile
{
    /** @return array<mixed> */
    public static function load(string $file): array
    {
        $data = require $file;

        if (!is_array($data)) {
            throw new \RuntimeException("El archivo $file debe devolver un arreglo.");
        }

        return $data;
    }

    /**
     * Claves como cadenas: "01" debe seguir siendo "01" aunque PHP lo guarde como entero.
     *
     * @param array<mixed> $values
     *
     * @return list<string>
     */
    public static function strings(array $values, string $file): array
    {
        $strings = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                throw new \RuntimeException("El archivo $file solo debe contener claves (cadenas o números).");
            }
            $strings[] = (string) $value;
        }

        return $strings;
    }
}
