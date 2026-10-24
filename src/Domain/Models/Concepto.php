<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

/** Datos tal como los captura el host; su forma la revisa RuleFormatoConceptos. */
final class Concepto
{
    /**
     * @param ImpuestoTrasladado[] $impuestosTrasladados
     * @param ImpuestoRetenido[] $impuestosRetenidos
     */
    public function __construct(
        public readonly string $claveProdServ,
        public readonly string $cantidad,
        public readonly string $claveUnidad,
        public readonly ?string $unidad,        // opcional en el SAT; null no escribe el atributo
        public readonly string $descripcion,
        public readonly string $valorUnitario,
        public readonly string $objetoImp,
        public readonly array $impuestosTrasladados,
        public readonly ?string $descuento = null,
        public readonly array $impuestosRetenidos = [],
    ) {}
}
