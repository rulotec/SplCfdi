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
}
