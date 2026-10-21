<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Support;

use SplCfdi\Domain\Exceptions\InvalidFieldException;

/** Única definición de las claves que son "N dígitos, como cadena" (códigos postales, ClaveProdServ). */
final class ClaveNumericaFormat
{
    private const DIGITOS_CODIGO_POSTAL = 5;
    private const DIGITOS_CLAVE_PROD_SERV = 8;

    /** @throws InvalidFieldException */
    public static function assertCodigoPostal(string $value, string $field): void
    {
        self::assertDigitos($value, $field, self::DIGITOS_CODIGO_POSTAL);
    }

    /** @throws InvalidFieldException */
    public static function assertClaveProdServ(string $value, string $field): void
    {
        self::assertDigitos($value, $field, self::DIGITOS_CLAVE_PROD_SERV);
    }

    private static function assertDigitos(string $value, string $field, int $digitos): void
    {
        if (preg_match(sprintf('/^[0-9]{%d}$/D', $digitos), $value) !== 1) {
            throw new InvalidFieldException($field, $value, sprintf(
                '%s inválido: "%s" (%d dígitos; si viene de un campo numérico, revisa los ceros a la izquierda).',
                $field,
                $value,
                $digitos
            ));
        }
    }
}
