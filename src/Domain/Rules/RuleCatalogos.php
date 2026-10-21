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
        $v = [];

        array_push($v, ...$this->verificar(CatalogName::TIPO_DE_COMPROBANTE, 'TipoDeComprobante', $c->tipoDeComprobante));
        array_push($v, ...$this->verificar(CatalogName::EXPORTACION, 'Exportacion', $c->exportacion));
        array_push($v, ...$this->verificar(CatalogName::METODO_PAGO, 'MetodoPago', $c->metodoPago));
        array_push($v, ...$this->verificar(CatalogName::FORMA_PAGO, 'FormaPago', $c->formaPago));
        array_push($v, ...$this->verificar(CatalogName::REGIMEN_FISCAL, 'RegimenFiscal del Emisor', $c->emisor->regimenFiscal));
        array_push($v, ...$this->verificar(CatalogName::REGIMEN_FISCAL, 'RegimenFiscalReceptor', $c->receptor->regimenFiscalReceptor));
        array_push($v, ...$this->verificar(CatalogName::USO_CFDI, 'UsoCFDI', $c->receptor->usoCFDI));

        if ($c->informacionGlobal !== null) {
            array_push($v, ...$this->verificar(CatalogName::PERIODICIDAD, 'Periodicidad', $c->informacionGlobal->periodicidad));
            array_push($v, ...$this->verificar(CatalogName::MESES, 'Meses', $c->informacionGlobal->meses));
        }

        foreach ($calculado->conceptos as $i => $cc) {
            $contexto = sprintf('Concepto %d: ', $i + 1);

            array_push($v, ...$this->verificar(CatalogName::OBJETO_IMP, 'ObjetoImp', $cc->concepto->objetoImp, $contexto));

            foreach ([...$cc->traslados, ...$cc->retenciones] as $impuesto) {
                array_push($v, ...$this->verificar(CatalogName::IMPUESTO, 'Impuesto', $impuesto->impuesto, $contexto));
            }
        }

        return $v;
    }

    /** @return Violation[] vacío si el valor es null (campo opcional) o existe */
    private function verificar(string $catalogo, string $campo, ?string $valor, string $contexto = ''): array
    {
        if ($valor === null || $this->catalogs->get($catalogo)->contains($valor)) {
            return [];
        }

        return [new Violation('catalogo-invalido', sprintf(
            '%s%s "%s" no existe en el catálogo %s.', $contexto, $campo, $valor, $catalogo
        ))];
    }
}
