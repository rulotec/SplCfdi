<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Support;

/** Única definición de "código postal válido" (cinco dígitos, como cadena). */
final class CodigoPostalFormat
{
    /** @throws \InvalidArgumentException */
    public static function assertValid(string $value, string $field): void
    {
        if (preg_match('/^\d{5}$/D', $value) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                '%s inválido: "%s" (cinco dígitos; si viene de un campo numérico, revisa los ceros a la izquierda).',
                $field,
                $value
            ));
        }
    }
}
