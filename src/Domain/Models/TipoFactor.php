<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

enum TipoFactor: string
{
    case Tasa = 'Tasa';
    /** La TasaOCuota es un monto por unidad (p. ej. IEPS por litro) y la Base son las unidades. */
    case Cuota = 'Cuota';
    case Exento = 'Exento';
}
