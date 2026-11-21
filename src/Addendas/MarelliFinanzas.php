<?php

declare(strict_types=1);

namespace SplCfdi\Addendas;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Addendas\{CampoAddenda, DatosAddenda, NivelCampo, TipoCampo};
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Rules\Violation;

/** Marelli Mexicana (namespace "finanzas"): un nodo Finanzas por concepto con su orden de compra y número de parte. */
final class MarelliFinanzas extends AddendaBase
{
    /** Las que admite el XSD en Finanzas/@Moneda. */
    private const MONEDAS = ['MXN', 'USD', 'EUR'];

    public function clave(): string
    {
        return 'MARELLI';
    }

    public function nombre(): string
    {
        return 'Marelli Mexicana';
    }

    public function namespace(): string
    {
        return 'finanzas';
    }

    protected function prefijo(): string
    {
        return 'fa';
    }

    public function campos(): array
    {
        return [
            new CampoAddenda('ordenCompra', 'Orden de compra', NivelCampo::Concepto, requerido: true),
            new CampoAddenda('posicion', 'Posición en la orden de compra', NivelCampo::Concepto, TipoCampo::Entero, true,
                patron: '[1-9][0-9]*', ayuda: 'número entero desde 1'),
            new CampoAddenda('numeroParte', 'Número de parte', NivelCampo::Concepto, requerido: true),
        ];
    }

    public function reglas(ComprobanteCalculado $calculado, DatosAddenda $datos): array
    {
        $moneda = $calculado->comprobante->moneda;
        if (in_array($moneda, self::MONEDAS, true)) {
            return [];
        }

        return [new Violation('addenda-moneda', sprintf(
            'Addenda %s: la moneda %s no se admite; solo %s.', $this->nombre(), $moneda, implode(', ', self::MONEDAS)
        ), 'Moneda')];
    }

    public function crear(DOMDocument $dom, ComprobanteCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $moneda = $calculado->comprobante->moneda;

        return $this->nodo($dom, 'Datos', [], [
            $this->nodo($dom, 'AddendaComercial', [], array_map(
                fn (int $i) => $this->nodo($dom, 'Finanzas', [
                    'PO' => $datos->valorConcepto($i, 'ordenCompra'),
                    'Linea' => $datos->valorConcepto($i, 'posicion'),
                    'Part' => $datos->valorConcepto($i, 'numeroParte'),
                    'Moneda' => $moneda,
                ]),
                array_keys($calculado->conceptos),
            )),
        ]);
    }
}
