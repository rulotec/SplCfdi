<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Support;

use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;

trait AssertsCfdiSchema
{
    /** El validador pone valores ficticios de sello al XML aún no sellado. */
    private function assertCumpleConElEsquema(string $xml): void
    {
        $violaciones = (new LibXmlSchemaValidator())->validate($xml);

        $this->assertSame([], array_map(fn (Violation $v) => "{$v->code} {$v->campo}: {$v->message}", $violaciones));
    }
}
