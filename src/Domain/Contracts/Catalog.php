<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Contracts;

interface Catalog
{
    /** Nombre del tipo en catCFDI.xsd, p. ej. "c_FormaPago". */
    public function name(): string;

    public function contains(string $key): bool;

    /** @return string[] */
    public function values(): array;
}
