<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\Comprobante;

/** Forma de los datos generales: códigos postales, moneda e InformacionGlobal. */
final class RuleFormatoComprobante implements FormatRule
{
    public function __construct(private readonly DecimalConfiguration $config) {}

    public function check(Comprobante $comprobante): array
    {
        $moneda = $comprobante->moneda;
        $info = $comprobante->informacionGlobal;

        $violaciones = [
            Formato::codigoPostal($comprobante->lugarExpedicion, 'LugarExpedicion', 'LugarExpedicion'),
            Formato::codigoPostal(
                $comprobante->receptor->domicilioFiscalReceptor,
                'DomicilioFiscalReceptor del Receptor',
                'Receptor.DomicilioFiscalReceptor'
            ),
        ];

        if (preg_match('/^[A-Z]{3}$/D', $moneda->codigo) !== 1) {
            $violaciones[] = new Violation('formato-moneda', sprintf(
                'Moneda "%s" no es válida: debe ser un código de tres letras mayúsculas (p. ej. "MXN", "USD").',
                $moneda->codigo
            ), 'Moneda');
        }

        if ($moneda->decimales < 0 || $moneda->decimales > $this->config->maximumScale) {
            $violaciones[] = new Violation('decimales-moneda', sprintf(
                'Los decimales de la moneda %s (%d) deben estar entre 0 y %d.',
                $moneda->codigo, $moneda->decimales, $this->config->maximumScale
            ), 'Moneda');
        }

        if ($info !== null && preg_match('/^\d{2}$/D', $info->meses) !== 1) {
            $violaciones[] = new Violation('formato-meses', sprintf(
                'Meses de InformacionGlobal "%s" no es válido: deben ser dos dígitos (p. ej. "04").', $info->meses
            ), 'InformacionGlobal.Meses');
        }

        if ($info !== null && ($info->anio < 1000 || $info->anio > 9999)) {
            $violaciones[] = new Violation('formato-anio', sprintf(
                'Año de InformacionGlobal (%d) no es válido: debe tener cuatro dígitos (p. ej. 2026).', $info->anio
            ), 'InformacionGlobal.Año');
        }

        return array_values(array_filter($violaciones));
    }
}
