<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Catalogs;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\Comprobante;

final class FormatRuleSet implements FormatRule
{
    /** @param FormatRule[] $rules */
    public function __construct(private readonly array $rules) {}

    public static function standard(DecimalConfiguration $config, Catalogs $catalogs): self
    {
        return new self([
            new RuleFormatoComprobante($config, $catalogs->monedas),
            new RuleFormatoRfc(),
            new RuleFormatoConceptos(),
        ]);
    }

    /** @return Violation[] todas las violaciones, no solo la primera */
    public function check(Comprobante $comprobante): array
    {
        return array_merge(...array_map(fn (FormatRule $rule) => $rule->check($comprobante), $this->rules));
    }
}
