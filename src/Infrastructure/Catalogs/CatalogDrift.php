<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

final class CatalogDrift
{
    /**
     * @param string[] $added   en el XSD pero no embebidos (nuevos del SAT)
     * @param string[] $removed embebidos pero ya no en el XSD (posiblemente dados de baja)
     */
    public function __construct(
        public readonly string $catalog,
        public readonly array $added,
        public readonly array $removed,
        public readonly bool $missingInXsd = false,
    ) {}
}
