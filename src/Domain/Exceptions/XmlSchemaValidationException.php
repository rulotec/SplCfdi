<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Exceptions;

final class XmlSchemaValidationException extends \RuntimeException
{
    /** @param string[] $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(
            "El XML no cumple con el esquema:\n- " . implode("\n- ", $errors)
        );
    }
}
