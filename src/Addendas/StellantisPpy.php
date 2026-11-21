<?php

declare(strict_types=1);

namespace SplCfdi\Addendas;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Addendas\{CampoAddenda, DatosAddenda, NivelCampo, TipoCampo};
use SplCfdi\Domain\Catalogs\Claves\{Impuesto, Moneda};
use SplCfdi\Domain\Models\{Comprobante, ComprobanteCalculado, ConceptoCalculado, TipoFactor};
use SplCfdi\Domain\Rules\Violation;

/** Stellantis (antes Chrysler) de México, addenda PPY, versión 1.0. */
final class StellantisPpy extends AddendaBase
{
    private const TIPOS_DOCUMENTO = ['I' => 'FA', 'E' => 'CR'];

    /** Tasa de IVA => código de otrosCargos. */
    private const CARGOS_IVA = ['0.000000' => 'V0', '0.010000' => 'V1', '0.110000' => 'V4', '0.160000' => 'V6'];

    /** Máximo de nodos otrosCargos que admite el XSD. */
    private const MAXIMO_CARGOS = 3;

    public function clave(): string
    {
        return 'PPY';
    }

    public function nombre(): string
    {
        return 'Stellantis México (PPY)';
    }

    public function namespace(): string
    {
        return 'http://www.dfdchryslerdemexico.com.mx/Addenda/PPY';
    }

    public function campos(): array
    {
        return [
            new CampoAddenda('codigoProveedor', 'Código de proveedor', NivelCampo::Cliente, requerido: true,
                longitudMinima: 4, longitudMaxima: 7, ayuda: 'asignado por Stellantis'),
            $this->campoTipoDocumento(['FA' => 'Factura', 'CA' => 'Nota de cargo', 'CR' => 'Nota de crédito'], 'FA en Ingreso, CR en Egreso'),
            new CampoAddenda('referenciaProveedor', 'Referencia del proveedor', NivelCampo::Comprobante, longitudMaxima: 15),
            new CampoAddenda('destinoCodigo', 'Código de la planta destino', NivelCampo::Comprobante, requerido: true,
                longitudMinima: 4, longitudMaxima: 7),
            new CampoAddenda('destinoNombre', 'Nombre de la planta destino', NivelCampo::Comprobante, requerido: true, longitudMaxima: 65),
            new CampoAddenda('ordenCompra', 'Orden de compra', NivelCampo::Concepto, longitudMaxima: 10),
            new CampoAddenda('releaseRequisicion', 'Release', NivelCampo::Concepto, longitudMaxima: 11),
            new CampoAddenda('ammendment', 'Nivel (amendment) de la orden de compra', NivelCampo::Concepto, longitudMaxima: 10,
                ayuda: 'obligatorio si hay orden de compra'),
            new CampoAddenda('numero', 'Número de parte', NivelCampo::Concepto, longitudMaxima: 30),
            new CampoAddenda('unidadDeMedida', 'Unidad de medida', NivelCampo::Concepto, longitudMaxima: 3,
                porDefecto: 'La Unidad del concepto si tiene hasta 3 caracteres; si no, su ClaveUnidad'),
            new CampoAddenda('nota', 'Nota', NivelCampo::Concepto),
        ];
    }

    public function porDefecto(Comprobante $comprobante): DatosAddenda
    {
        return new DatosAddenda(
            $this->clave(),
            [...$this->tipoDocumentoPorDefecto($comprobante, self::TIPOS_DOCUMENTO)],
            $this->unidadesPorDefecto($comprobante, 'unidadDeMedida', 3),
        );
    }

    public function reglas(ComprobanteCalculado $calculado, DatosAddenda $datos): array
    {
        $violaciones = [
            ...$this->reglaTipoDocumento($calculado, $datos),
            ...$this->reglaFolio($calculado, 15, 15),
        ];
        $c = $calculado->comprobante;

        $tasas = $this->tasasIva($calculado);
        foreach (array_keys($tasas) as $tasa) {
            if (!isset(self::CARGOS_IVA[$tasa])) {
                $violaciones[] = new Violation('addenda-tasa-iva', sprintf(
                    'Addenda %s: la tasa de IVA %s no tiene código de cargo en la addenda (solo 0%%, 1%%, 11%% y 16%%).', $this->nombre(), $tasa
                ), 'Addenda');
            }
        }
        if (count($tasas) > self::MAXIMO_CARGOS) {
            $violaciones[] = new Violation('addenda-tasa-iva', sprintf(
                'Addenda %s: admite a lo más %d tasas de IVA distintas.', $this->nombre(), self::MAXIMO_CARGOS
            ), 'Addenda');
        }

        foreach (array_keys($c->conceptos) as $i) {
            if ($datos->valorConcepto($i, 'ordenCompra') !== null && $datos->valorConcepto($i, 'ammendment') === null) {
                $violaciones[] = new Violation('addenda-requerido', sprintf(
                    'Addenda %s, concepto %d: con orden de compra también se necesita el nivel (amendment).', $this->nombre(), $i + 1
                ), sprintf('Addenda.Concepto[%d].ammendment', $i + 1));
            }
        }

        return $violaciones;
    }

    public function crear(DOMDocument $dom, ComprobanteCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $c = $calculado->comprobante;
        $conTipoCambio = $c->tipoCambio !== null && $c->moneda !== Moneda::PESO_MEXICANO;

        return $this->nodo($dom, 'factura', [
            'tipoDocumento' => 'PPY',
            'TipoDocumentoFiscal' => $datos->valor(self::TIPO_DOCUMENTO),
            'version' => '1.0',
            'serie' => $c->serie,
            'folioFiscal' => $c->folio,
            'fecha' => $c->fecha->format('Y-m-d'),
            'montoTotal' => $this->decimales($calculado->importes->total, 2),
            'referenciaProveedor' => $datos->valor('referenciaProveedor'),
        ], [
            $this->nodo($dom, 'moneda', [
                'tipoMoneda' => $c->moneda,
                'tipoCambio' => $conTipoCambio ? $this->decimales((string) $c->tipoCambio, 4) : null,
            ]),
            $this->nodo($dom, 'proveedor', [
                'codigo' => $datos->valor('codigoProveedor'),
                'nombre' => $this->recortar($c->emisor->nombre, 65),
            ]),
            $this->nodo($dom, 'destino', [
                'codigo' => $datos->valor('destinoCodigo'),
                'nombre' => $datos->valor('destinoNombre'),
            ]),
            ...$this->otrosCargos($dom, $calculado),
            $this->nodo($dom, 'partes', [], array_map(
                fn (int $i, ConceptoCalculado $concepto) => $this->parte($dom, $i, $concepto, $datos),
                array_keys($calculado->conceptos),
                $calculado->conceptos,
            )),
        ]);
    }

    /**
     * Un cargo por tasa de IVA trasladado; el XSD exige al menos uno, así que sin IVA va V0 en cero.
     *
     * @return DOMElement[]
     */
    private function otrosCargos(DOMDocument $dom, ComprobanteCalculado $calculado): array
    {
        $cargos = [];
        foreach ($this->tasasIva($calculado) as $tasa => $importe) {
            $cargos[] = $this->nodo($dom, 'otrosCargos', [
                'codigo' => self::CARGOS_IVA[$tasa] ?? null,
                'monto' => $this->decimales($importe, 2),
            ]);
        }

        return $cargos === [] ? [$this->nodo($dom, 'otrosCargos', ['codigo' => 'V0', 'monto' => '0.00'])] : $cargos;
    }

    /** @return array<string, string> tasa de IVA => importe trasladado */
    private function tasasIva(ComprobanteCalculado $calculado): array
    {
        $tasas = [];
        foreach ($calculado->impuestos->traslados as $traslado) {
            if ($traslado->impuesto === Impuesto::IVA && $traslado->tipoFactor === TipoFactor::Tasa && $traslado->tasaOCuota !== null) {
                $tasas[$traslado->tasaOCuota] = $traslado->importe ?? '0';
            }
        }

        return $tasas;
    }

    private function parte(DOMDocument $dom, int $i, ConceptoCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $concepto = $calculado->concepto;
        $ordenCompra = $datos->valorConcepto($i, 'ordenCompra');

        return $this->nodo($dom, 'part', [
            'numero' => $datos->valorConcepto($i, 'numero'),
            'cantidad' => $this->decimales($concepto->cantidad, 4),
            'unidadDeMedida' => $datos->valorConcepto($i, 'unidadDeMedida'),
            'precioUnitario' => $this->decimales($concepto->valorUnitario, 4),
            'montoDeLinea' => $this->decimales($calculado->importe, 2),
        ], [
            $ordenCompra === null ? null : $this->nodo($dom, 'references', [
                'ordenCompra' => $ordenCompra,
                'releaseRequisicion' => $datos->valorConcepto($i, 'releaseRequisicion'),
                'ammendment' => $datos->valorConcepto($i, 'ammendment'),
            ]),
            ...$this->notas($dom, 'nota', $datos->valorConcepto($i, 'nota'), 200),
        ]);
    }
}
