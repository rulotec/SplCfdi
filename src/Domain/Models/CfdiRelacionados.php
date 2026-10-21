<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

/** Nodo CfdiRelacionados: los UUID de los CFDI que se relacionan con un mismo TipoRelacion. */
final class CfdiRelacionados
{
    /**
     * @param string   $tipoRelacion c_TipoRelacion, p. ej. "01" nota de crédito, "04" sustitución
     * @param string[] $uuids        folios fiscales (UUID) de los CFDI relacionados
     */
    public function __construct(
        public readonly string $tipoRelacion,
        public readonly array $uuids,
    ) {}
}
