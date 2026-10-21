<?php

declare(strict_types=1);

namespace SplCfdi\Application;

use SplCfdi\Domain\Contracts\CfdiXmlGenerator;
use SplCfdi\Domain\Contracts\XmlSchemaValidator;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\FormatRule;
use SplCfdi\Domain\Rules\Rule;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Domain\Services\InvoiceCalculator;

final class CfdiService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly CfdiXmlGenerator $xmlGenerator,
        private readonly FormatRule $formatRules,                    // obligatorias: sin ellas el cálculo puede fallar
        private readonly ?Rule $rules = null,                        // null: sin validación de reglas del SAT
        private readonly ?XmlSchemaValidator $schemaValidator = null, // null: sin validación contra el XSD
    ) {}

    /**
     * 1. Formato de los datos: si falla, no se puede calcular y se devuelven solo esas violaciones.
     * 2. Cálculo, reglas del SAT y XSD: se devuelven todas las violaciones, junto con el cálculo.
     * 3. Sin violaciones: se devuelve el XML.
     */
    public function generate(Comprobante $comprobante): CfdiResult
    {
        return $this->procesar($comprobante, true);
    }

    /** Como generate(), pero el resultado nunca trae XML: útil para revisar un formulario mientras se captura. */
    public function validate(Comprobante $comprobante): CfdiResult
    {
        return $this->procesar($comprobante, false);
    }

    private function procesar(Comprobante $comprobante, bool $conXml): CfdiResult
    {
        $formato = $this->formatRules->check($comprobante);
        if ($formato !== []) {
            return new CfdiResult($formato);
        }

        $calculado = $this->calculator->calculate($comprobante);
        $violaciones = $this->rules?->check($calculado) ?? [];

        if ($this->schemaValidator === null && !$conXml) {
            return new CfdiResult($violaciones, $calculado);
        }

        $xml = $this->xmlGenerator->generate($calculado);
        $violaciones = [...$violaciones, ...$this->sinRepetir($this->schemaValidator?->validate($xml) ?? [], $violaciones)];

        return new CfdiResult($violaciones, $calculado, $conXml && $violaciones === [] ? $xml : null);
    }

    /**
     * Las reglas explican mejor el problema: se omite el error del XSD sobre un campo que ya tiene violación.
     *
     * @param Violation[] $xsd
     * @param Violation[] $reglas
     *
     * @return Violation[]
     */
    private function sinRepetir(array $xsd, array $reglas): array
    {
        $campos = array_filter(array_map(fn (Violation $v) => $v->campo, $reglas), fn (?string $c) => $c !== null);

        return array_values(array_filter($xsd, fn (Violation $v) => $v->campo === null || !in_array($v->campo, $campos, true)));
    }
}
