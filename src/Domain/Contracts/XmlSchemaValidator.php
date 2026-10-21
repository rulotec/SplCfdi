<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Contracts;

use SplCfdi\Domain\Rules\Violation;

interface XmlSchemaValidator
{
    /** @return Violation[] vacío si el XML cumple el esquema; los mensajes son para el usuario final */
    public function validate(string $xml): array;
}
