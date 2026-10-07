<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Contracts;

use SplCfdi\Domain\Exceptions\XmlSchemaValidationException;

interface XmlSchemaValidator
{
    /** @throws XmlSchemaValidationException si el XML no es válido contra el esquema */
    public function validate(string $xml): void;
}
