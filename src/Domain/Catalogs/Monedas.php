<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs;

/** c_Moneda con las columnas que solo trae catCFDI.xls: decimales y porcentaje de variación. */
final class Monedas
{
    /** @param array<string, array{descripcion: string, decimales: int, variacion: ?string}> $monedas */
    public function __construct(private readonly array $monedas) {}

    public function existe(string $codigo): bool
    {
        return isset($this->monedas[$codigo]);
    }

    /** Decimales con que se expresan los importes en esa moneda; null si no existe. */
    public function decimales(string $codigo): ?int
    {
        return $this->monedas[$codigo]['decimales'] ?? null;
    }

    /** Variación permitida del TipoCambio respecto al FIX, como fracción ("5.0000" = 500 %); null si no aplica. */
    public function variacion(string $codigo): ?string
    {
        return $this->monedas[$codigo]['variacion'] ?? null;
    }

    /** @return string[] */
    public function codigos(): array
    {
        return array_map('strval', array_keys($this->monedas));
    }
}
