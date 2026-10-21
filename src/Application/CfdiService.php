<?php

declare(strict_types=1);

namespace SplCfdi\Application;

use SplCfdi\Domain\Contracts\CfdiXmlGenerator;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\FormatRule;
use SplCfdi\Domain\Rules\Rule;
use SplCfdi\Domain\Services\InvoiceCalculator;

final class CfdiService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly CfdiXmlGenerator $xmlGenerator,
        private readonly FormatRule $formatRules,   // obligatorias: sin ellas el cálculo puede fallar
        private readonly ?Rule $rules = null,       // null: sin validación de reglas del SAT
    ) {}

    /**
     * 1. Formato de los datos: si falla, no se puede calcular y se devuelven solo esas violaciones.
     * 2. Cálculo y reglas del SAT: se devuelven todas las violaciones, junto con el cálculo.
     * 3. Sin violaciones: se genera el XML.
     */
    public function generate(Comprobante $comprobante): CfdiResult
    {
        $resultado = $this->validate($comprobante);

        if (!$resultado->isValid() || $resultado->calculado === null) {
            return $resultado;
        }

        return new CfdiResult([], $resultado->calculado, $this->xmlGenerator->generate($resultado->calculado));
    }

    /** Pasos 1 y 2 de generate(), sin XML: útil para revisar un formulario mientras se captura. */
    public function validate(Comprobante $comprobante): CfdiResult
    {
        $formato = $this->formatRules->check($comprobante);
        if ($formato !== []) {
            return new CfdiResult($formato);
        }

        $calculado = $this->calculator->calculate($comprobante);

        return new CfdiResult($this->rules?->check($calculado) ?? [], $calculado);
    }
}
