<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\Moneda;
use SplCfdi\Domain\Catalogs\Monedas;
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Contracts\TipoCambioFix;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/**
 * TipoCambio según la Moneda:
 *  - MXN: se omite o vale 1 (CFDI40114); XXX: no se registra (CFDI40116); otra: es obligatorio (CFDI40115).
 *  - Con un TipoCambioFix, fuera de FIX × (1 ± variación de c_Moneda) exige Confirmacion (CFDI40118).
 *    El límite inferior nunca es menor que cero.
 */
final class RuleTipoCambio implements Rule
{
    public function __construct(
        private readonly Monedas $monedas,
        private readonly DecimalMath $math,
        private readonly ?TipoCambioFix $fix = null,
    ) {}

    public function check(ComprobanteCalculado $calculado): array
    {
        $c = $calculado->comprobante;
        $tipoCambio = $c->tipoCambio;

        if ($c->moneda === Moneda::PESO_MEXICANO) {
            return $tipoCambio === null || $this->math->compare($tipoCambio, '1') === 0 ? [] : [new Violation(
                'tipo-cambio-mxn',
                sprintf('Con Moneda MXN el TipoCambio se omite o debe ser 1 (recibido: "%s").', $tipoCambio),
                'TipoCambio'
            )];
        }

        if ($c->moneda === Moneda::SIN_MONEDA) {
            return $tipoCambio === null ? [] : [new Violation(
                'tipo-cambio-xxx',
                sprintf('Con Moneda XXX (ninguna moneda) no debe registrarse TipoCambio (recibido: "%s").', $tipoCambio),
                'TipoCambio'
            )];
        }

        if ($tipoCambio === null) {
            return [new Violation('tipo-cambio-requerido', sprintf(
                'Con Moneda %s el TipoCambio es obligatorio: los pesos mexicanos que vale una unidad de %s.', $c->moneda, $c->moneda
            ), 'TipoCambio')];
        }

        $fueraDeRango = $this->fueraDeRango($c, $tipoCambio);

        return $fueraDeRango === null || $c->confirmacion !== null ? [] : [new Violation('tipo-cambio-fuera-de-rango', sprintf(
            'El TipoCambio %s está fuera del rango permitido para %s (%s a %s, según el FIX %s y la variación de c_Moneda): '
            . 'revisa el valor o solicita al PAC la clave de Confirmacion.',
            $tipoCambio, $c->moneda, $fueraDeRango[0], $fueraDeRango[1], $fueraDeRango[2]
        ), 'TipoCambio')];
    }

    /** @return array{string, string, string}|null [mínimo, máximo, FIX] si el TipoCambio está fuera; null si está dentro o no se puede saber */
    private function fueraDeRango(Comprobante $c, string $tipoCambio): ?array
    {
        $variacion = $this->monedas->variacion($c->moneda);
        $fix = $this->fix?->fix($c->moneda, $c->fecha);
        if ($variacion === null || $fix === null) {   // sin variación en c_Moneda o sin FIX: no se puede saber
            return null;
        }

        $minimo = $this->math->multiply($fix, $this->math->subtract('1', $variacion));
        if ($this->math->compare($minimo, '0') < 0) {
            $minimo = '0';
        }
        $maximo = $this->math->multiply($fix, $this->math->add('1', $variacion));

        if ($this->math->compare($tipoCambio, $minimo) >= 0 && $this->math->compare($tipoCambio, $maximo) <= 0) {
            return null;
        }

        return [$this->math->round($minimo, 6), $this->math->round($maximo, 6), $fix];
    }
}
