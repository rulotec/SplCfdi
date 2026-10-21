<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Support;

use SplCfdi\Domain\Rules\Violation;

trait AssertsRules
{
    /**
     * @param string[]    $esperados
     * @param Violation[] $violaciones
     */
    private function assertCodigos(array $esperados, array $violaciones): void
    {
        $this->assertSame($esperados, array_map(fn (Violation $v) => $v->code, $violaciones));
    }
}
