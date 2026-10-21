<?php

declare(strict_types=1);

namespace SplCfdi\Application;

use SplCfdi\Domain\Contracts\CfdiXmlGenerator;
use SplCfdi\Domain\Exceptions\CfdiRulesViolationException;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\Rule;
use SplCfdi\Domain\Services\InvoiceCalculator;

final class CfdiService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly CfdiXmlGenerator $xmlGenerator,
        private readonly ?Rule $rules = null,   // null: sin validación de reglas
    ) {}

    public function generate(Comprobante $comprobante): GeneratedCfdi
    {
        $calculado = $this->calculator->calculate($comprobante);

        $violaciones = $this->rules?->check($calculado) ?? [];
        if ($violaciones !== []) {
            throw new CfdiRulesViolationException($violaciones);
        }

        return new GeneratedCfdi($calculado, $this->xmlGenerator->generate($calculado));
    }
}
