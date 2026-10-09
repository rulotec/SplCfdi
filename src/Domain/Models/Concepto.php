<?php
declare(strict_types=1);

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Support\DecimalFormat;

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
        public readonly string $unidad,
        public readonly string $descripcion,
        public readonly string $valorUnitario,
        public readonly string $objetoImp,
        public readonly array $impuestosTrasladados,
        public readonly ?string $descuento = null,
        public readonly array $impuestosRetenidos = [],
        ) {
            $max = DecimalConfiguration::SAT_MAX_SCALE;

            DecimalFormat::assertValid($cantidad, 'Cantidad', $max);
            DecimalFormat::assertValid($valorUnitario, 'ValorUnitario', $max);

            if ($descuento !== null) {
                DecimalFormat::assertValid($descuento, 'Descuento', $max);
            }
    }
}
