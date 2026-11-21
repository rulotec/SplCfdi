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

    /**
     * Estos datos con lo que les falte tomado de $respaldo (p. ej. Addenda::porDefecto()). Lo de este objeto
     * gana siempre, también un dato de concepto puesto en $valores para todos los conceptos.
     */
    public function completadoCon(self $respaldo): self
    {
        $valores = array_filter($this->valores, fn (string $valor) => self::capturado($valor) !== null);
        foreach ($respaldo->valores as $nombre => $valor) {
            $valores[$nombre] ??= $valor;
        }

        $conceptos = $this->conceptos;
        foreach ($respaldo->conceptos as $indice => $deRespaldo) {
            foreach ($deRespaldo as $nombre => $valor) {
                if ($this->valorConcepto($indice, $nombre) === null) {
                    $conceptos[$indice][$nombre] = $valor;
                }
            }
        }

        return new self($this->clave, $valores, $conceptos);
    }

    private static function capturado(?string $valor): ?string
    {
        return $valor === null || trim($valor) === '' ? null : $valor;
    }
}
