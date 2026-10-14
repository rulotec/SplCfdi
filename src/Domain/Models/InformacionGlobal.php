<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class InformacionGlobal
{
    private const BIMESTRAL = '05';

    /**
     * @param string $periodicidad c_Periodicidad: 01 Diario … 05 Bimestral
     * @param string $meses        c_Meses: 01–12, o 13–18 si la periodicidad es bimestral
     */
    public function __construct(
        public readonly string $periodicidad,
        public readonly string $meses,
        public readonly int $anio,
    ) {
        if (!preg_match('/^\d{2}$/D', $meses)) {
            throw new \InvalidArgumentException(sprintf('Meses inválido: "%s" (dos dígitos, p. ej. "04").', $meses));
        }

        [$min, $max] = $periodicidad === self::BIMESTRAL ? [13, 18] : [1, 12];

        if ((int) $meses < $min || (int) $meses > $max) {
            throw new \InvalidArgumentException(sprintf(
                'Meses "%s" no corresponde a la periodicidad "%s" (se esperaba %02d a %02d).',
                $meses, $periodicidad, $min, $max
            ));
        }

        if ($anio < 1000 || $anio > 9999) {
            throw new \InvalidArgumentException(sprintf('Año inválido: %d (cuatro dígitos).', $anio));
        }
    }
}
