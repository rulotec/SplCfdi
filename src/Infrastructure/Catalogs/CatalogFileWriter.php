<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

/** Genera el contenido de resources/catalogs/<c_Nombre>.php. */
final class CatalogFileWriter
{
    /** @param string[] $values */
    public static function render(string $name, array $values): string
    {
        $lines = array_map(
            fn (string $value) => '    ' . var_export($value, true) . ',',
            array_map('strval', array_values($values))
        );

        return "<?php\n\n"
            . "// $name: generado por bin/cfdi-catalogs write desde catCFDI.xsd. No editar a mano.\n\n"
            . "return [\n" . implode("\n", $lines) . "\n];\n";
    }

    /**
     * Archivo de datos tabulares de catCFDI.xls (resources/catalogs/xls/<c_Nombre>.php).
     *
     * @param array<mixed> $data
     */
    public static function renderData(string $name, array $data): string
    {
        return "<?php\n\n"
            . "// $name: generado por bin/cfdi-catalogs xls desde catCFDI.xls. No editar a mano.\n\n"
            . 'return ' . self::export($data, 0) . ";\n";
    }

    private static function export(mixed $value, int $nivel): string
    {
        if (!is_array($value)) {
            return $value === null ? 'null' : var_export($value, true);
        }

        $sangria = str_repeat('    ', $nivel + 1);
        $esLista = array_is_list($value);
        $lineas = [];
        foreach ($value as $clave => $item) {
            $prefijo = $esLista ? '' : var_export((string) $clave, true) . ' => ';
            $lineas[] = $sangria . $prefijo . self::export($item, $nivel + 1) . ',';
        }

        return "[\n" . implode("\n", $lineas) . "\n" . str_repeat('    ', $nivel) . ']';
    }
}
