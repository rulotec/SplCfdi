<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Services;

use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Models\ImpuestoRetenidoCalculado;
use SplCfdi\Domain\Models\ImpuestoRetenidoResumen;

final class ImpuestoRetenidoAggregator
{
    public function __construct(private readonly DecimalMath $math) {}

    /**
     * @param ImpuestoRetenidoCalculado[] $retenciones
     *
     * @return ImpuestoRetenidoResumen[] importes sin redondear; el redondeo es del calculador
     */
    public function aggregate(array $retenciones): array
    {
        $totales = [];

        foreach ($retenciones as $retencion) {
            $totales[$retencion->impuesto] = $this->math->add(
                $totales[$retencion->impuesto] ?? '0',
                $retencion->importe
            );
        }

        $resumen = [];
        foreach ($totales as $impuesto => $importe) {
            $resumen[] = new ImpuestoRetenidoResumen((string) $impuesto, $importe);
        }

        return $resumen;
    }
}
