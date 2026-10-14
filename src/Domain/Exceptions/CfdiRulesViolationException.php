<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Exceptions;

use SplCfdi\Domain\Rules\Violation;

final class CfdiRulesViolationException extends \RuntimeException
{
    /** @param Violation[] $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(
            "El CFDI no cumple las reglas del SAT:\n- "
            . implode("\n- ", array_map(fn (Violation $v) => $v->message, $violations))
        );
    }
}
