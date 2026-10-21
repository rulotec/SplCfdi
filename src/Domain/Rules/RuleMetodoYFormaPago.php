<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\FormaPago;
use SplCfdi\Domain\Catalogs\Claves\MetodoPago;
use SplCfdi\Domain\Catalogs\Claves\TipoDeComprobante;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/**
 * MetodoPago y FormaPago:
 *  - sin FormaPago en tipos T, N y P (CFDI40103); sin MetodoPago en T y P (CFDI40125);
 *  - con PPD la FormaPago es 99 (CFDI40105); con PUE no puede ser 99, porque el pago ya se recibió
 *    (Guía de llenado del Anexo 20, campo FormaPago).
 */
final class RuleMetodoYFormaPago implements Rule
{
    public function check(ComprobanteCalculado $calculado): array
    {
        $c = $calculado->comprobante;
        $tipo = $c->tipoDeComprobante;
        $violaciones = [];

        if ($c->formaPago !== null && TipoDeComprobante::sinFormaPago($tipo)) {
            $violaciones[] = new Violation('forma-pago-no-aplica', sprintf(
                'Cuando el TipoDeComprobante es %s no debe registrarse FormaPago (recibido: "%s").', $tipo, $c->formaPago
            ), 'FormaPago');
        }

        if ($c->metodoPago !== null && TipoDeComprobante::sinMetodoPago($tipo)) {
            $violaciones[] = new Violation('metodo-pago-no-aplica', sprintf(
                'Cuando el TipoDeComprobante es %s no debe registrarse MetodoPago (recibido: "%s").', $tipo, $c->metodoPago
            ), 'MetodoPago');
        }

        if ($c->metodoPago === MetodoPago::PPD && $c->formaPago !== FormaPago::POR_DEFINIR && !TipoDeComprobante::sinFormaPago($tipo)) {
            $violaciones[] = new Violation('ppd-requiere-forma-pago-99', sprintf(
                'Con MetodoPago PPD (pago en parcialidades o diferido) la FormaPago debe ser "%s" (Por definir); recibido: %s.',
                FormaPago::POR_DEFINIR, $c->formaPago === null ? 'no informada' : '"' . $c->formaPago . '"'
            ), 'FormaPago');
        }

        if ($c->metodoPago === MetodoPago::PUE && $c->formaPago === FormaPago::POR_DEFINIR && !TipoDeComprobante::sinFormaPago($tipo)) {
            $violaciones[] = new Violation('pue-con-forma-pago-99', sprintf(
                'Con MetodoPago PUE (pago en una sola exhibición) la FormaPago no puede ser "%s" (Por definir): '
                . 'indica cómo se recibió el pago, o usa PPD si aún no se recibe.',
                FormaPago::POR_DEFINIR
            ), 'FormaPago');
        }

        return $violaciones;
    }
}
