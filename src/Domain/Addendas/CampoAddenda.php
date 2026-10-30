<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Addendas;

/**
 * Un dato que la addenda necesita y que el CFDI no trae: el host lo captura y lo entrega en DatosAddenda.
 * Lo que sale del CFDI (montos, cantidades, folio, moneda, descripción) no es campo: la addenda lo calcula.
 */
final class CampoAddenda
{
    /**
     * @param string                $nombre     clave en DatosAddenda; aparece en Violation::$campo ("Addenda.ordenCompra")
     * @param string                $etiqueta   para el usuario final
     * @param array<string, string> $opciones   valor => descripción; solo con TipoCampo::Opcion
     * @param string|null           $porDefecto qué usa la addenda si se omite (p. ej. "La Moneda del CFDI")
     */
    public function __construct(
        public readonly string $nombre,
        public readonly string $etiqueta,
        public readonly NivelCampo $nivel,
        public readonly TipoCampo $tipo = TipoCampo::Texto,
        public readonly bool $requerido = false,
        public readonly ?int $longitudMinima = null,
        public readonly ?int $longitudMaxima = null,
        public readonly ?string $patron = null,     // expresión regular sin delimitadores; debe cumplirse completa
        public readonly array $opciones = [],
        public readonly ?string $ayuda = null,
        public readonly ?string $porDefecto = null,
    ) {
        if (($tipo === TipoCampo::Opcion) !== ($opciones !== [])) {
            throw new \InvalidArgumentException("Campo de addenda $nombre: las opciones van solo, y siempre, con TipoCampo::Opcion.");
        }
    }
}
