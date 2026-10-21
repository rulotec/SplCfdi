<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs;

use SplCfdi\Domain\Models\TipoPersona;

/** c_RegimenFiscal con las columnas Física/Moral y la descripción de catCFDI.xls. */
final class RegimenesFiscales
{
    /** @param array<string, array{descripcion: string, fisica: bool, moral: bool}> $regimenes */
    public function __construct(private readonly array $regimenes) {}

    /** null si el régimen no existe (eso lo reporta RuleCatalogos). */
    public function aplicaA(string $regimen, TipoPersona $persona): ?bool
    {
        $fila = $this->regimenes[$regimen] ?? null;

        return match (true) {
            $fila === null => null,
            $persona === TipoPersona::Fisica => $fila['fisica'],
            default => $fila['moral'],
        };
    }

    public function descripcion(string $regimen): ?string
    {
        return $this->regimenes[$regimen]['descripcion'] ?? null;
    }

    /** @return string[] */
    public function claves(): array
    {
        return array_map('strval', array_keys($this->regimenes));
    }
}
