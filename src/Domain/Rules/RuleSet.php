<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Catalogs;
use SplCfdi\Domain\Contracts\Clock;
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Contracts\TipoCambioFix;
use SplCfdi\Domain\Models\ComprobanteCalculado;

final class RuleSet implements Rule
{
    /** @param Rule[] $rules */
    public function __construct(private readonly array $rules) {}

    /** @param TipoCambioFix|null $fix sin él no se revisa el rango del TipoCambio */
    public static function standard(Clock $clock, Catalogs $catalogs, DecimalMath $math, ?TipoCambioFix $fix = null): self
    {
        return new self([
            new RuleFechaDentroDeVentana($clock),
            new RuleInformacionGlobal(),
            new RuleDomicilioReceptorGenerico(),
            new RuleSinImpuestosEnTrasladoOPago(),
            new RuleInformacionGlobalAnio(),
            new RuleMesesSegunPeriodicidad(),
            new RuleBimestralSoloRif(),
            new RuleMetodoPagoPueEnGlobal(),
            new RuleMetodoYFormaPago(),
            new RuleTipoCambio($catalogs->monedas, $math, $fix),
            new RuleRfcGenerico(),
            new RuleRegimenFiscalPersona($catalogs->regimenesFiscales),
            new RuleDescuentoPorTipoDeComprobante(),
            new RuleRegimenUsoCfdi(),
            new RuleObjetoImpCoherente(),
            new RuleTasaOCuota($catalogs->tasasOCuotas, $math),
            new RuleDescuentoNoMayorQueImporte(),
            new RuleBaseNoCero(),
            new RuleTotalNoNegativo(),
            new RuleCatalogos($catalogs),
        ]);
    }

    /** @return Violation[] todas las violaciones, no solo la primera */
    public function check(ComprobanteCalculado $calculado): array
    {
        return array_merge(...array_map(fn (Rule $rule) => $rule->check($calculado), $this->rules));
    }
}
