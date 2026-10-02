<?php

namespace SplCfdi\Domain\Contracts;

use SplCfdi\Domain\Models\ComprobanteCalculado;

interface CfdiXmlGenerator
{
    public function generate(ComprobanteCalculado $comprobante): string;
}
