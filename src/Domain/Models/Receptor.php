<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Support\CodigoPostalFormat;

final class Receptor
{
    public function __construct(
        public readonly string $rfc,
        public readonly string $nombre,
        public readonly string $domicilioFiscalReceptor,
        public readonly string $regimenFiscalReceptor,
        public readonly string $usoCFDI
    ) {
        CodigoPostalFormat::assertValid($domicilioFiscalReceptor, 'DomicilioFiscalReceptor');
    }
}