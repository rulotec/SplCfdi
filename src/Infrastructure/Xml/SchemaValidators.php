<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Xml;

use SplCfdi\Domain\Contracts\XmlSchemaValidator;

/** Varias validaciones de esquema como una sola (el CFDI y sus addendas). */
final class SchemaValidators implements XmlSchemaValidator
{
    /** @var XmlSchemaValidator[] */
    private readonly array $validators;

    public function __construct(XmlSchemaValidator ...$validators)
    {
        $this->validators = $validators;
    }

    public function validate(string $xml): array
    {
        return array_merge(...array_map(fn (XmlSchemaValidator $v) => $v->validate($xml), $this->validators));
    }
}
