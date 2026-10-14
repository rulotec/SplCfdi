<?php
// src/Domain/Rules/RuleSet.php
declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Contracts\Clock;
use SplCfdi\Domain\Models\ComprobanteCalculado;

final class RuleSet implements Rule
{
    /** @param Rule[] $rules */
    public function __construct(private readonly array $rules) {}

    public static function standard(Clock $clock): self
    {
        return new self([
            new RuleFechaDentroDeVentana($clock),
            new RuleInformacionGlobal(),
            new RuleDomicilioReceptorGenerico(),
            new RuleSinImpuestosEnTrasladoOPago(),
        ]);
    }

    /** @return Violation[] todas las violaciones, no solo la primera */
    public function check(ComprobanteCalculado $calculado): array
    {
        return array_merge(...array_map(fn (Rule $rule) => $rule->check($calculado), $this->rules));
    }
}
