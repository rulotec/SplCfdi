<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Support;

use SplCfdi\Domain\Exceptions\XmlSchemaValidationException;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;

trait AssertsCfdiSchema
{
    /** El XML generado aún no está sellado: se le agregan marcadores de sello antes de validar. */
    private function assertCumpleConElEsquema(string $xml): void
    {
        try {
            (new LibXmlSchemaValidator())->validate(Fixtures::withSealPlaceholders($xml));
        } catch (XmlSchemaValidationException $e) {
            $this->fail($e->getMessage());
        }

        $this->addToAssertionCount(1);
    }
}
