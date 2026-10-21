<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Support;

/** Única definición de "ClaveProdServ con forma válida" (ocho dígitos, como cadena). */
final class ClaveProdServFormat
{
    /** @throws \InvalidArgumentException */
    public static function assertValid(string $value, string $field): void
    {
        if (preg_match('/^[0-9]{8}$/D', $value) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                '%s inválido: "%s" (ocho dígitos; si viene de un campo numérico, revisa los ceros a la izquierda).',
                $field,
                $value
            ));
        }
    }
}
