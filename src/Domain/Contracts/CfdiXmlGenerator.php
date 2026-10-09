<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Contracts;

use SplCfdi\Domain\Models\ComprobanteCalculado;

interface CfdiXmlGenerator
{
    public function generate(ComprobanteCalculado $comprobante): string;
}
