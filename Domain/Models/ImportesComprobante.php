<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class ImportesComprobante
{
    public function __construct(
        public readonly string $subTotal,
        public readonly string $total,
        public readonly ?string $descuento = null
    ) {}
}
