<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

/** Valores del XSD que no deben formar parte de los catálogos embebidos (retirados por el SAT). */
final class CatalogExclusions
{
    /** @param array<string, string[]> $excluded */
    public function __construct(private readonly array $excluded) {}

    public static function load(string $dir): self
    {
        $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '_excluded.php';

        if (!is_file($file)) {
            return new self([]);
        }

        $excluded = [];
        foreach (PhpDataFile::load($file) as $catalog => $values) {
            if (!is_string($catalog) || !is_array($values)) {
                throw new \RuntimeException("El archivo $file debe tener la forma ['c_Catalogo' => ['clave', ...]].");
            }
            $excluded[$catalog] = PhpDataFile::strings($values, $file);
        }

        return new self($excluded);
    }

    /** @return array<string, string[]> */
    public function all(): array
    {
        return $this->excluded;
    }

    /**
     * @param array<string, string[]> $xsd
     *
     * @return array<string, string[]>
     */
    public function filter(array $xsd): array
    {
        foreach ($xsd as $name => $values) {
            $xsd[$name] = array_values(array_diff($values, $this->excluded[$name] ?? []));
        }

        return $xsd;
    }
}
