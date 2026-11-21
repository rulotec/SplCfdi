<?php

declare(strict_types=1);

namespace SplCfdi\Addendas;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Addendas\{Addenda, CampoAddenda, DatosAddenda, NivelCampo, TipoCampo};
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Models\{Comprobante, ComprobanteCalculado, Concepto};
use SplCfdi\Domain\Rules\{Formato, Violation};
use SplCfdi\Infrastructure\Resources;

/**
 * Base de las addendas incluidas: el XSD en resources/addendas/<clave>.xsd, el prefijo igual a la clave y
 * utilerías para armar nodos. Una addenda puede implementar Addenda directamente si no le sirve.
 */
abstract class AddendaBase implements Addenda
{
    /** Nombre del campo opcional con que el usuario fija el tipo de documento en vez de derivarlo. */
    protected const TIPO_DOCUMENTO = 'tipoDocumentoFiscal';

    public function __construct(protected readonly DecimalMath $math) {}

    public function esquema(): ?string
    {
        return Resources::path('addendas/' . $this->clave() . '.xsd');
    }

    public function porDefecto(Comprobante $comprobante): DatosAddenda
    {
        return new DatosAddenda($this->clave());
    }

    public function reglas(ComprobanteCalculado $calculado, DatosAddenda $datos): array
    {
        return [];
    }

    protected function prefijo(): string
    {
        return $this->clave();
    }

    /**
     * @param array<string, ?string> $atributos los null se omiten
     * @param array<?DOMElement>     $hijos     los null se omiten
     */
    protected function nodo(DOMDocument $dom, string $nombre, array $atributos = [], array $hijos = []): DOMElement
    {
        $nodo = $dom->createElementNS($this->namespace(), $this->prefijo() . ':' . $nombre);
        foreach ($atributos as $atributo => $valor) {
            if ($valor !== null) {
                $nodo->setAttribute($atributo, $valor);
            }
        }
        foreach ($hijos as $hijo) {
            if ($hijo !== null) {
                $nodo->appendChild($hijo);
            }
        }

        return $nodo;
    }

    /**
     * Un nodo de texto por cada trozo de $longitud caracteres (las notas largas se reparten en varios nodos).
     *
     * @return DOMElement[]
     */
    protected function notas(DOMDocument $dom, string $nombre, ?string $texto, int $longitud): array
    {
        if ($texto === null) {
            return [];
        }

        return array_map(function (string $trozo) use ($dom, $nombre) {
            $nodo = $this->nodo($dom, $nombre);
            $nodo->appendChild($dom->createTextNode($trozo));

            return $nodo;
        }, mb_str_split($texto, max(1, $longitud)));
    }

    protected function decimales(string $valor, int $decimales): string
    {
        return $this->math->round($valor, $decimales);
    }

    protected function recortar(?string $texto, int $longitud): ?string
    {
        return $texto === null ? null : mb_substr(trim($texto), 0, $longitud);
    }

    /** La Unidad del concepto si cabe en $longitud caracteres; si no (o si no tiene), su ClaveUnidad. */
    protected function unidad(Concepto $concepto, ?int $longitud = null): string
    {
        $unidad = $concepto->unidad;

        return $unidad !== null && trim($unidad) !== '' && ($longitud === null || mb_strlen($unidad) <= $longitud)
            ? $unidad
            : $concepto->claveUnidad;
    }

    /**
     * El campo opcional que fija el tipo de documento del cliente.
     *
     * @param array<string, string> $opciones valor => descripción
     */
    protected function campoTipoDocumento(array $opciones, string $porDefecto): CampoAddenda
    {
        return new CampoAddenda(
            self::TIPO_DOCUMENTO, 'Tipo de documento', NivelCampo::Comprobante, TipoCampo::Opcion,
            opciones: $opciones, porDefecto: $porDefecto,
        );
    }

    /**
     * Para porDefecto(): el tipo de documento que corresponde al TipoDeComprobante, si hay uno.
     *
     * @param array<string, string> $porTipoDeComprobante TipoDeComprobante => tipo de documento del cliente
     *
     * @return array<string, string>
     */
    protected function tipoDocumentoPorDefecto(Comprobante $comprobante, array $porTipoDeComprobante): array
    {
        $tipo = $porTipoDeComprobante[$comprobante->tipoDeComprobante] ?? null;

        return $tipo === null ? [] : [self::TIPO_DOCUMENTO => $tipo];
    }

    /**
     * Para porDefecto(): el campo $nombre de cada concepto con su unidad (ver unidad()).
     *
     * @return array<int, array<string, string>>
     */
    protected function unidadesPorDefecto(Comprobante $comprobante, string $nombre, ?int $longitud = null): array
    {
        return array_values(array_map(fn (Concepto $concepto) => [$nombre => $this->unidad($concepto, $longitud)], $comprobante->conceptos));
    }

    /**
     * Sin tipo de documento capturado ni uno que corresponda al TipoDeComprobante.
     *
     * @return Violation[]
     */
    protected function reglaTipoDocumento(ComprobanteCalculado $calculado, DatosAddenda $datos): array
    {
        if ($datos->valor(self::TIPO_DOCUMENTO) !== null) {
            return [];
        }

        return [new Violation('addenda-tipo-documento', sprintf(
            'Addenda %s: no hay un tipo de documento para el TipoDeComprobante "%s"; indica el tipo de documento.',
            $this->nombre(),
            $calculado->comprobante->tipoDeComprobante,
        ), 'Addenda.' . self::TIPO_DOCUMENTO)];
    }

    /**
     * Para las addendas que copian Serie y Folio del comprobante: el Folio es obligatorio y ambos tienen un
     * máximo de caracteres.
     *
     * @return Violation[]
     */
    protected function reglaFolio(ComprobanteCalculado $calculado, int $maximoFolio, int $maximoSerie): array
    {
        $c = $calculado->comprobante;
        if ($c->folio === null || trim($c->folio) === '') {
            return [new Violation('addenda-requiere-folio', "Addenda {$this->nombre()}: el comprobante necesita Folio.", 'Folio')];
        }

        $lugar = "Addenda {$this->nombre()}";

        return array_values(array_filter([
            Formato::longitud($c->folio, "$lugar: Folio", 'Folio', 'addenda-longitud', maximo: $maximoFolio),
            $c->serie === null ? null : Formato::longitud($c->serie, "$lugar: Serie", 'Serie', 'addenda-longitud', maximo: $maximoSerie),
        ]));
    }
}
