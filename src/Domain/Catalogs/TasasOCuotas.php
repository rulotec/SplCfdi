<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs;

use DateTimeImmutable;

/** c_TasaOCuota (solo en catCFDI.xls). Los rangos cambian cada año, por eso se filtra por vigencia. */
final class TasasOCuotas
{
    /** @param TasaOCuota[] $filas */
    public function __construct(private readonly array $filas) {}

    /** @return TasaOCuota[] las filas que aplican a ese impuesto, factor y uso, vigentes en la fecha */
    public function aplicables(string $impuesto, string $tipoFactor, bool $esRetencion, DateTimeImmutable $fecha): array
    {
        return array_values(array_filter(
            $this->filas,
            fn (TasaOCuota $f) => $f->impuesto === $impuesto
                && $f->tipoFactor === $tipoFactor
                && ($esRetencion ? $f->retencion : $f->traslado)
                && $f->vigenteEn($fecha)
        ));
    }
}
