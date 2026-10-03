<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class Concepto
{
    /**
     * @param ImpuestoTrasladado[] $impuestosTrasladados
     */
    public function __construct(
        public readonly string $claveProdServ,
        public readonly string $cantidad,
        public readonly string $claveUnidad,
        public readonly string $unidad,
        public readonly string $descripcion,
        public readonly string $valorUnitario,
        public readonly string $importe,
        public readonly string $objetoImp,
        public readonly array $impuestosTrasladados
    ) {}
}