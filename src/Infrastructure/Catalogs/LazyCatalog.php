<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

use Closure;
use SplCfdi\Domain\Catalogs\ArrayCatalog;
use SplCfdi\Domain\Contracts\Catalog;

/** Lee su archivo la primera vez que se consulta: c_CodigoPostal (~95 000 claves) no se carga si no se usa. */
final class LazyCatalog implements Catalog
{
    private ?ArrayCatalog $catalog = null;

    /** @param Closure(): list<string> $load */
    public function __construct(private readonly string $name, private readonly Closure $load) {}

    public function name(): string
    {
        return $this->name;
    }

    public function contains(string $key): bool
    {
        return $this->catalog()->contains($key);
    }

    public function values(): array
    {
        return $this->catalog()->values();
    }

    private function catalog(): ArrayCatalog
    {
        return $this->catalog ??= new ArrayCatalog($this->name, ($this->load)());
    }
}
