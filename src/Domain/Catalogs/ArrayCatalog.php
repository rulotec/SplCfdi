<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs;

use SplCfdi\Domain\Contracts\Catalog;

final class ArrayCatalog implements Catalog
{
    /** @var string[] */
    private readonly array $values;

    /** @var array<string, true> */
    private readonly array $index;

    /** @param iterable<int, scalar> $values */
    public function __construct(private readonly string $name, iterable $values)
    {
        $list = [];
        foreach ($values as $value) {
            $list[] = (string) $value;   // "01" debe seguir siendo cadena
        }

        $this->values = $list;
        $this->index = array_fill_keys($list, true);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function contains(string $key): bool
    {
        return isset($this->index[$key]);
    }

    public function values(): array
    {
        return $this->values;
    }
}
