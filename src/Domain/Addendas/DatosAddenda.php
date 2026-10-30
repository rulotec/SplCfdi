<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Addendas;

/**
 * Lo que el host captura para la addenda del comprobante: los valores de los campos de Addenda::campos().
 *
 * Un campo de nivel Concepto puede ir en $valores: se aplica a todos los conceptos que no lo traigan en
 * $conceptos (p. ej. una misma orden de compra para toda la factura).
 */
final class DatosAddenda
{
    /**
     * @param string                              $clave     Addenda::clave()
     * @param array<string, string>               $valores   campos de nivel Cliente y Comprobante
     * @param array<int, array<string, string>>   $conceptos índice de Comprobante::$conceptos (desde 0) => valores
     */
    public function __construct(
        public readonly string $clave,
        public readonly array $valores = [],
        public readonly array $conceptos = [],
    ) {}

    /** null si no se capturó o está en blanco. */
    public function valor(string $nombre): ?string
    {
        return self::capturado($this->valores[$nombre] ?? null);
    }

    /** El valor del concepto o, si no lo trae, el de $valores. $indice empieza en 0. */
    public function valorConcepto(int $indice, string $nombre): ?string
    {
        return self::capturado($this->conceptos[$indice][$nombre] ?? null) ?? $this->valor($nombre);
    }

    private static function capturado(?string $valor): ?string
    {
        return $valor === null || trim($valor) === '' ? null : $valor;
    }
}
