<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\UsoCfdiMatrix;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\TipoPersona;

/**
 * UsoCFDI debe ser válido para el tipo de persona (por longitud del RFC) y para el RegimenFiscalReceptor.
 * Un uso fuera de la matriz no se reporta aquí: que exista en el catálogo lo verifica RuleCatalogos,
 * y `bin/cfdi-catalogs check` avisa de usos del catálogo que falten en la matriz.
 */
final class RuleRegimenUsoCfdi implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $receptor = $calculado->comprobante->receptor;
        $uso = UsoCfdiMatrix::find($receptor->usoCFDI);

        if ($uso === null) {
            return [];
        }

        $violaciones = [];

        // Un RFC de otra longitud lo reporta RuleFormatoRfc.
        $persona = TipoPersona::deRfc($receptor->rfc);
        $permitida = match ($persona) {
            TipoPersona::Moral => $uso['moral'],
            TipoPersona::Fisica => $uso['fisica'],
            null => true,
        };
        if (!$permitida) {
            $violaciones[] = new Violation('uso-cfdi-persona', sprintf(
                'UsoCFDI "%s" no aplica a una persona %s (RFC %s).',
                $receptor->usoCFDI, $persona->value, $receptor->rfc
            ), 'Receptor.UsoCFDI');
        }

        if (!in_array($receptor->regimenFiscalReceptor, $uso['regimenes'], true)) {
            $violaciones[] = new Violation('uso-cfdi-regimen', sprintf(
                'UsoCFDI "%s" no es válido para el RegimenFiscalReceptor "%s".',
                $receptor->usoCFDI, $receptor->regimenFiscalReceptor
            ), 'Receptor.UsoCFDI');
        }

        return $violaciones;
    }
}
