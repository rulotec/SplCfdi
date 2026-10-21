<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs;

use DateTimeImmutable;

/** Una fila de c_TasaOCuota: un valor fijo (minimo = null) o un rango, para un impuesto y factor. */
final class TasaOCuota
{
    public function __construct(
        public readonly string $impuesto,     // c_Impuesto: 001 ISR, 002 IVA, 003 IEPS
        public readonly string $tipoFactor,   // Tasa o Cuota
        public readonly ?string $minimo,      // null: valor fijo igual a $maximo
        public readonly string $maximo,
        public readonly bool $traslado,
        public readonly bool $retencion,
        public readonly string $inicioVigencia,   // Y-m-d
        public readonly ?string $finVigencia,     // Y-m-d; null = vigente
    ) {}

    public function esRango(): bool
    {
        return $this->minimo !== null;
    }

    public function vigenteEn(DateTimeImmutable $fecha): bool
    {
        $dia = $fecha->format('Y-m-d');

        return $dia >= $this->inicioVigencia && ($this->finVigencia === null || $dia <= $this->finVigencia);
    }

    /** "0.160000" o "0.000000 a 0.350000", para los mensajes. */
    public function describir(): string
    {
        return $this->minimo === null ? $this->maximo : "{$this->minimo} a {$this->maximo}";
    }
}
