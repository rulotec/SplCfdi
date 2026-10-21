<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs;

use SplCfdi\Domain\Contracts\Catalog;

final class Catalogs
{
    /** @var array<string, Catalog> */
    private array $byName = [];

    /** @param Catalog[] $catalogs */
    public function __construct(array $catalogs)
    {
        foreach ($catalogs as $catalog) {
            $this->byName[$catalog->name()] = $catalog;
        }
    }

    public function get(string $name): Catalog
    {
        return $this->byName[$name] ?? throw new \InvalidArgumentException("Catálogo desconocido: $name");
    }
}
