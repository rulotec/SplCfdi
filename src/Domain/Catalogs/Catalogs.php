<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs;

use SplCfdi\Domain\Contracts\Catalog;

final class Catalogs
{
    /** @var array<string, Catalog> */
    private array $byName = [];

    /** @param Catalog[] $catalogs listas de claves (de catCFDI.xsd) */
    public function __construct(
        array $catalogs,
        public readonly Monedas $monedas,
        public readonly RegimenesFiscales $regimenesFiscales,
        public readonly TasasOCuotas $tasasOCuotas,
    ) {
        foreach ($catalogs as $catalog) {
            $this->byName[$catalog->name()] = $catalog;
        }
    }

    public function get(string $name): Catalog
    {
        return $this->byName[$name] ?? throw new \InvalidArgumentException("Catálogo desconocido: $name");
    }
}
