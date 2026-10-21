<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\RegimenesFiscales;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\TipoPersona;

/**
 * El RegimenFiscal debe aplicar al tipo de persona según las columnas Física/Moral de c_RegimenFiscal:
 * del emisor (CFDI40141) y del receptor (CFDI40158). El tipo de persona sale de la longitud del RFC.
 */
final class RuleRegimenFiscalPersona implements Rule
{
    public function __construct(private readonly RegimenesFiscales $regimenes) {}

    public function check(ComprobanteCalculado $calculado): array
    {
        $c = $calculado->comprobante;

        return array_values(array_filter([
            $this->revisar('regimen-emisor-persona', 'del Emisor', 'Emisor.RegimenFiscal', $c->emisor->rfc, $c->emisor->regimenFiscal),
            $this->revisar(
                'regimen-receptor-persona', 'del Receptor', 'Receptor.RegimenFiscalReceptor',
                $c->receptor->rfc, $c->receptor->regimenFiscalReceptor
            ),
        ]));
    }

    private function revisar(string $code, string $quien, string $campo, string $rfc, string $regimen): ?Violation
    {
        $persona = TipoPersona::deRfc($rfc);
        if ($persona === null || $this->regimenes->aplicaA($regimen, $persona) !== false) {
            return null;   // RFC mal formado (RuleFormatoRfc) o régimen inexistente (RuleCatalogos)
        }

        return new Violation($code, sprintf(
            'El RegimenFiscal %s "%s" (%s) no aplica a una persona %s (RFC %s).',
            $quien, $regimen, $this->regimenes->descripcion($regimen) ?? '', $persona->value, $rfc
        ), $campo);
    }
}
