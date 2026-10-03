<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class Emisor
{
    public function __construct(
        public readonly string $rfc,
        public readonly string $nombre,
        public readonly string $regimenFiscal
    ) {}
}