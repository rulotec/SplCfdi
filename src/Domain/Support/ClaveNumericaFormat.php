<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Support;

/** Única definición de las claves que son "N dígitos, como cadena" (códigos postales, ClaveProdServ). */
final class ClaveNumericaFormat
{
    public const DIGITOS_CODIGO_POSTAL = 5;
    public const DIGITOS_CLAVE_PROD_SERV = 8;

    public static function esCodigoPostal(string $value): bool
    {
        return self::tieneDigitos($value, self::DIGITOS_CODIGO_POSTAL);
    }

    public static function esClaveProdServ(string $value): bool
    {
        return self::tieneDigitos($value, self::DIGITOS_CLAVE_PROD_SERV);
    }

    private static function tieneDigitos(string $value, int $digitos): bool
    {
        return preg_match(sprintf('/^[0-9]{%d}$/D', $digitos), $value) === 1;
    }
}
