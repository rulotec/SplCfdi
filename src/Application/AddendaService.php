<?php

declare(strict_types=1);

namespace SplCfdi\Application;

use SplCfdi\Domain\Contracts\XmlSchemaValidator;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\FormatRule;
use SplCfdi\Domain\Rules\Rule;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Domain\Services\InvoiceCalculator;
use SplCfdi\Infrastructure\Addendas\AddendaWriter;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;

/**
 * Agrega la addenda a un CFDI ya timbrado (para PACs que no la aceptan, o datos que llegan después).
 * No revisa las reglas del SAT ni el XSD del CFDI: el comprobante ya fue aceptado. Solo la addenda.
 */
final class AddendaService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly FormatRule $formatRules,
        private readonly Rule $addendaRules,                            // RuleAddenda
        private readonly AddendaWriter $writer,
        private readonly ?XmlSchemaValidator $schemaValidator = null,   // AddendaSchemaValidator
    ) {}

    /**
     * @param string      $xml         el CFDI timbrado; fuera de cfdi:Addenda no se modifica
     * @param Comprobante $comprobante los mismos datos con que se generó, con su DatosAddenda
     *
     * @return CfdiResult con el XML (con la addenda) solo si no hay violaciones
     */
    public function add(string $xml, Comprobante $comprobante): CfdiResult
    {
        $formato = $this->formatRules->check($comprobante);
        if ($formato !== []) {
            return new CfdiResult($formato);
        }

        $calculado = $this->calculator->calculate($comprobante);
        if ($comprobante->addenda === null) {
            return new CfdiResult([new Violation('addenda-sin-datos', 'El comprobante no indica qué addenda agregar.', 'Addenda')], $calculado);
        }

        $violaciones = $this->addendaRules->check($calculado);
        if ($violaciones !== []) {
            return new CfdiResult($violaciones, $calculado);
        }

        $dom = LibXmlSchemaValidator::cargar($xml);
        if (is_string($dom)) {
            return new CfdiResult([new Violation('xml-mal-formado', $dom)], $calculado);
        }

        try {
            $conAddenda = $this->writer->write($xml, $calculado);
        } catch (\InvalidArgumentException $e) {
            return new CfdiResult([new Violation('xml-no-cfdi', $e->getMessage())], $calculado);
        }

        $violaciones = $this->schemaValidator?->validate($conAddenda) ?? [];

        return new CfdiResult($violaciones, $calculado, $violaciones === [] ? $conAddenda : null);
    }
}
