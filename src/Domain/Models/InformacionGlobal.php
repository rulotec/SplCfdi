<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class InformacionGlobal
{
    /**
     * @param string $periodicidad c_Periodicidad: 01 Diario … 05 Bimestral
     * @param string $meses        c_Meses: 01–12, o 13–18 si la periodicidad es bimestral
     */
    public function __construct(
        public readonly string $periodicidad,
        public readonly string $meses,
        public readonly int $anio,
    ) {}
}
