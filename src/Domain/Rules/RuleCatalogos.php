<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Domain\Catalogs\Catalogs;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/**
 * Los valores de campos de catálogo deben existir en el catálogo embebido.
 * Solo cubre los catálogos pequeños; ClaveProdServ, ClaveUnidad y códigos postales se validan
 * por forma en los modelos y por existencia contra el XSD.
 */
final class RuleCatalogos implements Rule
{
    public function __construct(private readonly Catalogs $catalogs) {}

    public function check(ComprobanteCalculado $calculado): array
    {
        $c = $calculado->comprobante;

        // [catálogo, etiqueta para el mensaje, ruta en el XML, valor]
        $revisar = [
            [CatalogName::TIPO_DE_COMPROBANTE, 'TipoDeComprobante', 'TipoDeComprobante', $c->tipoDeComprobante],
            [CatalogName::EXPORTACION, 'Exportacion', 'Exportacion', $c->exportacion],
            [CatalogName::METODO_PAGO, 'MetodoPago', 'MetodoPago', $c->metodoPago],
            [CatalogName::FORMA_PAGO, 'FormaPago', 'FormaPago', $c->formaPago],
            [CatalogName::REGIMEN_FISCAL, 'RegimenFiscal del Emisor', 'Emisor.RegimenFiscal', $c->emisor->regimenFiscal],
            [CatalogName::REGIMEN_FISCAL, 'RegimenFiscalReceptor', 'Receptor.RegimenFiscalReceptor', $c->receptor->regimenFiscalReceptor],
            [CatalogName::USO_CFDI, 'UsoCFDI', 'Receptor.UsoCFDI', $c->receptor->usoCFDI],
            [CatalogName::PERIODICIDAD, 'Periodicidad', 'InformacionGlobal.Periodicidad', $c->informacionGlobal?->periodicidad],
            [CatalogName::MESES, 'Meses', 'InformacionGlobal.Meses', $c->informacionGlobal?->meses],
        ];

        foreach ($calculado->conceptos as $i => $cc) {
            $etiqueta = Ubicacion::concepto($i, $cc->concepto);
            $revisar[] = [CatalogName::OBJETO_IMP, "$etiqueta: ObjetoImp", Ubicacion::campoConcepto($i, 'ObjetoImp'), $cc->concepto->objetoImp];

            foreach ($cc->traslados as $t) {
                $revisar[] = [CatalogName::IMPUESTO, "$etiqueta: Impuesto", Ubicacion::campoConcepto($i, 'Traslado.Impuesto'), $t->impuesto];
            }
            foreach ($cc->retenciones as $r) {
                $revisar[] = [CatalogName::IMPUESTO, "$etiqueta: Impuesto", Ubicacion::campoConcepto($i, 'Retencion.Impuesto'), $r->impuesto];
            }
        }

        $violaciones = [];
        foreach ($revisar as [$catalogo, $etiqueta, $campo, $valor]) {
            // null: campo opcional no informado
            if ($valor !== null && !$this->catalogs->get($catalogo)->contains($valor)) {
                $violaciones[] = new Violation('catalogo-invalido', sprintf(
                    '%s "%s" no existe en el catálogo %s.', $etiqueta, $valor, $catalogo
                ), $campo);
            }
        }

        return $violaciones;
    }
}
