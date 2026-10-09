<?php
declare(strict_types=1);

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Configuration\DecimalConfiguration;

final class Concepto
{
    /** @param ImpuestoTrasladado[] $impuestosTrasladados */
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
    ) {
        self::validarDecimal($cantidad, 'Cantidad');
        self::validarDecimal($valorUnitario, 'ValorUnitario');

        if ($descuento !== null) {
            self::validarDecimal($descuento, 'Descuento');
        }
    }

    private static function validarDecimal(string $valor, string $campo): void
    {
        $patron = sprintf('/^\d+(?:\.\d{1,%d})?$/D', DecimalConfiguration::SAT_MAX_SCALE);

        if (!preg_match($patron, $valor)) {
            throw new \InvalidArgumentException(sprintf(
                    '%s inválido: "%s" (decimal no negativo, máximo %d decimales).',
                    $campo, $valor, DecimalConfiguration::SAT_MAX_SCALE
                )
            );
        }
    }
}