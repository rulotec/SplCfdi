<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Monedas;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\Comprobante;

/** Forma de los datos generales: códigos postales, moneda, TipoCambio, Confirmacion, InformacionGlobal y CfdiRelacionados. */
final class RuleFormatoComprobante implements FormatRule
{
    private const PATRON_UUID = '/^[a-f0-9A-F]{8}-[a-f0-9A-F]{4}-[a-f0-9A-F]{4}-[a-f0-9A-F]{4}-[a-f0-9A-F]{12}$/D';

    public function __construct(
        private readonly DecimalConfiguration $config,
        private readonly Monedas $monedas,
    ) {}

    public function check(Comprobante $comprobante): array
    {
        $info = $comprobante->informacionGlobal;

        $violaciones = [
            Formato::codigoPostal($comprobante->lugarExpedicion, 'LugarExpedicion', 'LugarExpedicion'),
            Formato::codigoPostal(
                $comprobante->receptor->domicilioFiscalReceptor,
                'DomicilioFiscalReceptor del Receptor',
                'Receptor.DomicilioFiscalReceptor'
            ),
            ...$this->revisarMoneda($comprobante),
        ];

        if ($comprobante->confirmacion !== null && preg_match('/^[0-9a-zA-Z]{5}$/D', $comprobante->confirmacion) !== 1) {
            $violaciones[] = new Violation('formato-confirmacion', sprintf(
                'Confirmacion "%s" no es válida: es la clave de cinco letras o números que entrega el PAC.',
                $comprobante->confirmacion
            ), 'Confirmacion');
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

        foreach (array_values($comprobante->cfdiRelacionados) as $i => $relacionados) {
            $campo = sprintf('CfdiRelacionados[%d]', $i + 1);

            if ($relacionados->uuids === []) {
                $violaciones[] = new Violation('cfdi-relacionados-vacio', sprintf(
                    'CfdiRelacionados con TipoRelacion "%s" no tiene ningún UUID.', $relacionados->tipoRelacion
                ), $campo);
            }

            foreach (array_values($relacionados->uuids) as $u => $uuid) {
                if (preg_match(self::PATRON_UUID, $uuid) !== 1) {
                    $violaciones[] = new Violation('formato-uuid', sprintf(
                        'UUID "%s" no es válido: debe ser el folio fiscal de 36 caracteres (p. ej. "5FB2822E-396D-4725-8521-CDC4BDD20CCF").',
                        $uuid
                    ), sprintf('%s.CfdiRelacionado[%d].UUID', $campo, $u + 1));
                }
            }
        }

        return array_values(array_filter($violaciones));
    }

    /** @return Violation[] */
    private function revisarMoneda(Comprobante $comprobante): array
    {
        $moneda = $comprobante->moneda;
        $decimales = $this->monedas->decimales($moneda);
        $violaciones = [];

        if ($decimales === null) {
            $violaciones[] = new Violation('moneda-desconocida', sprintf(
                'Moneda "%s" no existe en el catálogo c_Moneda: usa el código ISO 4217 de tres letras (p. ej. "MXN", "USD").',
                $moneda
            ), 'Moneda');
        } elseif ($decimales > $this->config->maximumScale) {
            $violaciones[] = new Violation('decimales-moneda', sprintf(
                'La moneda %s usa %d decimales y la configuración solo admite %d.',
                $moneda, $decimales, $this->config->maximumScale
            ), 'Moneda');
        }

        if ($comprobante->tipoCambio !== null
            && (preg_match('/^[0-9]{1,18}(\.[0-9]{1,6})?$/D', $comprobante->tipoCambio) !== 1
                || preg_match('/^[0.]+$/D', $comprobante->tipoCambio) === 1)
        ) {
            $violaciones[] = new Violation('formato-tipo-cambio', sprintf(
                'TipoCambio "%s" no es válido: debe ser un número mayor que cero con máximo 6 decimales (p. ej. "17.512300").',
                $comprobante->tipoCambio
            ), 'TipoCambio');
        }

        return $violaciones;
    }
}
