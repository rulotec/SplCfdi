<?php

declare(strict_types=1);

namespace SplCfdi\Application\AddendaForm;

use SplCfdi\Domain\Addendas\AddendaRegistry;
use SplCfdi\Domain\Contracts\{CfdiXmlGenerator, XmlSchemaValidator};
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\{FormatRule, Rule};
use SplCfdi\Domain\Services\InvoiceCalculator;

/**
 * Builds the addenda form of an invoice: the host's defaults, what the user typed, and the problems of
 * the resulting addenda. It checks only the addenda (its fields, its rules and its XSD), not the SAT
 * rules: the form is about the addenda, and the invoice has its own validation.
 *
 * Create it with CfdiFactory::createAddendaFormService().
 */
final class AddendaFormService
{
    public function __construct(
        private readonly AddendaRegistry $addendas,
        private readonly InvoiceCalculator $calculator,
        private readonly FormatRule $formatRules,
        private readonly Rule $addendaRules,                            // RuleAddenda
        private readonly CfdiXmlGenerator $generator,                   // with AddendaXmlGenerator
        private readonly ?XmlSchemaValidator $schemaValidator = null,   // AddendaSchemaValidator
    ) {}

    /**
     * $comprobante's own addenda is ignored: the one built from $defaults and $overrides replaces it.
     *
     * @param array<int, string> $lineNotices line index (from 0) => what the user should check on that line,
     *                                        e.g. that its product changed since the data was typed
     */
    public function build(
        Comprobante $comprobante,
        string $key,
        AddendaDefaults $defaults,
        AddendaOverrides $overrides,
        array $lineNotices = [],
    ): AddendaForm {
        $addenda = $this->addendas->get($key);
        $values = $addenda === null ? null : new AddendaValues($addenda, $defaults, $overrides, count($comprobante->conceptos));
        $form = new AddendaForm($key, $addenda, $values, $comprobante->conceptos, $lineNotices);
        $comprobante = $comprobante->conAddenda($form->datos());

        // Without the invoice's format right it can't be calculated, and the addenda can't be built.
        $format = $this->formatRules->check($comprobante);
        if ($format !== []) {
            return $form->checked($format, null);
        }

        $calculado = $this->calculator->calculate($comprobante);
        $violations = $this->addendaRules->check($calculado);
        if ($violations === [] && $this->schemaValidator !== null) {
            // Only now: while the rules fail, the XSD would just repeat them.
            $violations = $this->schemaValidator->validate($this->generator->generate($calculado));
        }

        return $form->checked($violations, $calculado);
    }
}
