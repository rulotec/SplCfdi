<?php

declare(strict_types=1);

namespace SplCfdi\Application;

use SplCfdi\Domain\Contracts\CfdiXmlGenerator;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Services\InvoiceCalculator;

final class CfdiService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly CfdiXmlGenerator $xmlGenerator,
    ) {}

    public function generate(Comprobante $comprobante): GeneratedCfdi
    {
        $calculado = $this->calculator->calculate($comprobante);

        return new GeneratedCfdi($calculado, $this->xmlGenerator->generate($calculado));
    }
}
