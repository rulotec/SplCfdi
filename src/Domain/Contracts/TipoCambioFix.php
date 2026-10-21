<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Contracts;

/**
 * Tipo de cambio FIX que publica el Banco de México. Lo provee el host (API del Banxico, una tabla propia…):
 * con él se revisa que el TipoCambio del CFDI esté dentro de la variación que permite c_Moneda.
 */
interface TipoCambioFix
{
    /** @return numeric-string|null pesos por unidad de la moneda; null si no se conoce (no se valida el rango) */
    public function fix(string $moneda, \DateTimeImmutable $fecha): ?string;
}
