<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ComprobanteCalculado;

/**
 * Forma del RFC del Emisor y del Receptor: 3 letras (moral) o 4 (física), fecha AAMMDD real y 3 de homoclave.
 * No verifica el dígito verificador ni que el RFC esté en la LCO del SAT.
 */
final class RuleFormatoRfc implements Rule
{
    private const PATRON = '/^[A-ZÑ&]{3,4}([0-9]{2})([0-9]{2})([0-9]{2})[A-Z0-9]{3}$/Du';

    public function check(ComprobanteCalculado $calculado): array
    {
        $comprobante = $calculado->comprobante;
        $violaciones = [];

        foreach (['Emisor' => $comprobante->emisor->rfc, 'Receptor' => $comprobante->receptor->rfc] as $campo => $rfc) {
            if (!$this->esValido($rfc)) {
                $violaciones[] = new Violation('rfc-formato', sprintf('Rfc del %s inválido: "%s".', $campo, $rfc));
            }
        }

        return $violaciones;
    }

    private function esValido(string $rfc): bool
    {
        if (preg_match(self::PATRON, $rfc, $m) !== 1) {
            return false;
        }

        [, $aa, $mm, $dd] = array_map('intval', $m);

        // El RFC solo trae dos dígitos de año: basta con que la fecha exista en 19AA o en 20AA.
        return checkdate($mm, $dd, 1900 + $aa) || checkdate($mm, $dd, 2000 + $aa);
    }
}
