<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Catalogs\Claves\Impuesto;
use SplCfdi\Domain\Catalogs\TasaOCuota;
use SplCfdi\Domain\Catalogs\TasasOCuotas;
use SplCfdi\Domain\Contracts\DecimalMath;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\TipoFactor;

/**
 * TasaOCuota de cada traslado y retención contra c_TasaOCuota: un valor fijo o dentro de un rango, según
 * impuesto, TipoFactor y si es traslado o retención, vigente en la Fecha del comprobante (CFDI40179, CFDI40185).
 */
final class RuleTasaOCuota implements Rule
{
    private const IMPUESTOS_CONOCIDOS = [Impuesto::ISR, Impuesto::IVA, Impuesto::IEPS];

    public function __construct(
        private readonly TasasOCuotas $tasas,
        private readonly DecimalMath $math,
    ) {}

    public function check(ComprobanteCalculado $calculado): array
    {
        $fecha = $calculado->comprobante->fecha;
        $violaciones = [];

        foreach ($calculado->conceptos as $i => $cc) {
            $etiqueta = Ubicacion::concepto($i, $cc->concepto);
            $grupos = ['Traslado' => $cc->traslados, 'Retencion' => $cc->retenciones];

            foreach ($grupos as $nodo => $impuestos) {
                foreach (array_values($impuestos) as $n => $impuesto) {
                    // Exento no lleva tasa; un impuesto fuera de c_Impuesto ya lo reporta RuleCatalogos.
                    if ($impuesto->tipoFactor === TipoFactor::Exento
                        || $impuesto->tasaOCuota === null
                        || !in_array($impuesto->impuesto, self::IMPUESTOS_CONOCIDOS, true)
                    ) {
                        continue;
                    }

                    $esRetencion = $nodo === 'Retencion';
                    $aplicables = $this->tasas->aplicables($impuesto->impuesto, $impuesto->tipoFactor->value, $esRetencion, $fecha);

                    if (!$this->admitida($impuesto->tasaOCuota, $aplicables)) {
                        $violaciones[] = new Violation('tasa-o-cuota-invalida', $this->mensaje(
                            $etiqueta, $impuesto->tasaOCuota, $impuesto->impuesto, $impuesto->tipoFactor, $esRetencion, $aplicables
                        ), Ubicacion::campoConcepto($i, sprintf('%s[%d].TasaOCuota', $nodo, $n + 1)));
                    }
                }
            }
        }

        return $violaciones;
    }

    /** @param TasaOCuota[] $aplicables */
    private function admitida(string $valor, array $aplicables): bool
    {
        foreach ($aplicables as $fila) {
            $dentro = $fila->minimo === null
                ? $this->math->compare($valor, $fila->maximo) === 0
                : $this->math->compare($valor, $fila->minimo) >= 0 && $this->math->compare($valor, $fila->maximo) <= 0;

            if ($dentro) {
                return true;
            }
        }

        return false;
    }

    /** @param TasaOCuota[] $aplicables */
    private function mensaje(
        string $etiqueta,
        string $valor,
        string $impuesto,
        TipoFactor $factor,
        bool $esRetencion,
        array $aplicables,
    ): string {
        $uso = $esRetencion ? 'la retención' : 'el traslado';
        $nombre = Impuesto::nombre($impuesto);

        if ($aplicables === []) {
            return sprintf(
                '%s: el SAT no admite %s de %s con TipoFactor %s (TasaOCuota recibida: %s).',
                $etiqueta, $uso, $nombre, $factor->value, $valor
            );
        }

        return sprintf(
            '%s: TasaOCuota %s no es válida para %s de %s con TipoFactor %s; valores permitidos: %s.',
            $etiqueta, $valor, $uso, $nombre, $factor->value,
            implode(', ', array_map(fn (TasaOCuota $f) => $f->describir(), $aplicables))
        );
    }
}
