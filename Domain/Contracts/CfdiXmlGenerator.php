<?php

namespace SplCfdi\Domain\Contracts;

use SplCfdi\Domain\Models\Comprobante;

interface CfdiXmlGenerator
{
    public function generate(Comprobante $comprobante): string;
}
