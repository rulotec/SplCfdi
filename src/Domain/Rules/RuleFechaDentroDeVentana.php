<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use DateTimeImmutable;
use SplCfdi\Domain\Contracts\Clock;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/** La Fecha no puede ser futura ni tener más de 72 horas respecto al momento de certificar. */
final class RuleFechaDentroDeVentana implements Rule
{
    private const MAX_HORAS = 72;

    public function __construct(private readonly Clock $clock) {}

    public function check(ComprobanteCalculado $calculado): array
    {
        $ahora = $this->clock->now();

        // El SAT lee la hora del XML como hora local del emisor, sin zona:
        // se reinterpreta en la zona del reloj (la del emisor).
        $fecha = new DateTimeImmutable($calculado->comprobante->fechaCfdi(), $ahora->getTimezone());
        $segundos = $ahora->getTimestamp() - $fecha->getTimestamp();

        if ($segundos < 0) {
            return [new Violation('fecha-futura', sprintf(
                'La Fecha (%s) es posterior a la hora actual del emisor (%s).',
                $fecha->format(Comprobante::FORMATO_FECHA), $ahora->format(Comprobante::FORMATO_FECHA)
            ), 'Fecha')];
        }

        if ($segundos > self::MAX_HORAS * 3600) {
            return [new Violation('fecha-vencida', sprintf(
                'La Fecha (%s) excede las %d horas permitidas para certificar (hora actual del emisor: %s).',
                $fecha->format(Comprobante::FORMATO_FECHA), self::MAX_HORAS, $ahora->format(Comprobante::FORMATO_FECHA)
            ), 'Fecha')];
        }

        return [];
    }
}
