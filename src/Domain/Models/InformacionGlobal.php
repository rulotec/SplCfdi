<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Catalogs\Claves\Meses;
use SplCfdi\Domain\Exceptions\InvalidFieldException;

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
    ) {
        if (!preg_match('/^\d{2}$/D', $meses)) {
            throw new InvalidFieldException('Meses', $meses, sprintf(
                'Meses inválido: "%s" (dos dígitos, p. ej. "04").', $meses
            ));
        }

        [$primero, $ultimo] = Meses::rangoPara($periodicidad);

        if ((int) $meses < (int) $primero || (int) $meses > (int) $ultimo) {
            throw new InvalidFieldException('Meses', $meses, sprintf(
                'Meses "%s" no corresponde a la periodicidad "%s" (se esperaba %s a %s).',
                $meses, $periodicidad, $primero, $ultimo
            ));
        }

        if ($anio < 1000 || $anio > 9999) {
            throw new InvalidFieldException('Año', (string) $anio, sprintf('Año inválido: %d (cuatro dígitos).', $anio));
        }
    }
}
