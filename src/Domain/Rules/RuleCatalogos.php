<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Domain\Catalogs\Catalogs;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/**
 * Los valores de campos de catálogo deben existir en el catálogo embebido. Incluye los grandes
 * (ClaveProdServ, ClaveUnidad, códigos postales), que la copia de validación del XSD solo revisa por forma.
 * c_Moneda la revisa RuleFormatoComprobante: sin moneda conocida no se puede calcular.
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
            [CatalogName::CODIGO_POSTAL, 'LugarExpedicion', 'LugarExpedicion', $c->lugarExpedicion],
            [CatalogName::REGIMEN_FISCAL, 'RegimenFiscal del Emisor', 'Emisor.RegimenFiscal', $c->emisor->regimenFiscal],
            [CatalogName::REGIMEN_FISCAL, 'RegimenFiscalReceptor', 'Receptor.RegimenFiscalReceptor', $c->receptor->regimenFiscalReceptor],
            [CatalogName::USO_CFDI, 'UsoCFDI', 'Receptor.UsoCFDI', $c->receptor->usoCFDI],
            [CatalogName::CODIGO_POSTAL, 'DomicilioFiscalReceptor', 'Receptor.DomicilioFiscalReceptor', $c->receptor->domicilioFiscalReceptor],
            [CatalogName::PERIODICIDAD, 'Periodicidad', 'InformacionGlobal.Periodicidad', $c->informacionGlobal?->periodicidad],
            [CatalogName::MESES, 'Meses', 'InformacionGlobal.Meses', $c->informacionGlobal?->meses],
        ];

        foreach (array_values($c->cfdiRelacionados) as $i => $relacionados) {
            $campo = sprintf('CfdiRelacionados[%d].TipoRelacion', $i + 1);
            $revisar[] = [CatalogName::TIPO_RELACION, 'TipoRelacion', $campo, $relacionados->tipoRelacion];
        }

        foreach ($calculado->conceptos as $i => $cc) {
            $etiqueta = Ubicacion::concepto($i, $cc->concepto);
            $revisar[] = [CatalogName::CLAVE_PROD_SERV, "$etiqueta: ClaveProdServ", Ubicacion::campoConcepto($i, 'ClaveProdServ'), $cc->concepto->claveProdServ];
            $revisar[] = [CatalogName::CLAVE_UNIDAD, "$etiqueta: ClaveUnidad", Ubicacion::campoConcepto($i, 'ClaveUnidad'), $cc->concepto->claveUnidad];
            $revisar[] = [CatalogName::OBJETO_IMP, "$etiqueta: ObjetoImp", Ubicacion::campoConcepto($i, 'ObjetoImp'), $cc->concepto->objetoImp];

            foreach (array_values($cc->traslados) as $n => $t) {
                $campo = Ubicacion::campoConcepto($i, sprintf('Traslado[%d].Impuesto', $n + 1));
                $revisar[] = [CatalogName::IMPUESTO, "$etiqueta: Impuesto", $campo, $t->impuesto];
            }
            foreach (array_values($cc->retenciones) as $n => $r) {
                $campo = Ubicacion::campoConcepto($i, sprintf('Retencion[%d].Impuesto', $n + 1));
                $revisar[] = [CatalogName::IMPUESTO, "$etiqueta: Impuesto", $campo, $r->impuesto];
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
