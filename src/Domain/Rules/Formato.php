<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Support\ClaveNumericaFormat;
use SplCfdi\Domain\Support\DecimalFormat;

/** Violaciones de formato con un mismo texto para el usuario final, sin importar el campo. */
final class Formato
{
    /** Número no negativo con hasta $maxDecimales decimales; null si es válido. */
    public static function decimal(string $valor, string $etiqueta, string $campo, int $maxDecimales): ?Violation
    {
        if (DecimalFormat::matches($valor, $maxDecimales)) {
            return null;
        }

        return new Violation('formato-decimal', sprintf(
            '%s "%s" no es válido: debe ser un número no negativo con máximo %d decimales, sin comas ni símbolos (p. ej. "1500.50").',
            $etiqueta, $valor, $maxDecimales
        ), $campo);
    }

    /** Código postal de cinco dígitos; null si es válido. */
    public static function codigoPostal(string $valor, string $etiqueta, string $campo): ?Violation
    {
        return ClaveNumericaFormat::esCodigoPostal($valor)
            ? null
            : self::digitos('formato-codigo-postal', $valor, $etiqueta, $campo, ClaveNumericaFormat::DIGITOS_CODIGO_POSTAL);
    }

    /** ClaveProdServ de ocho dígitos; null si es válida. */
    public static function claveProdServ(string $valor, string $etiqueta, string $campo): ?Violation
    {
        return ClaveNumericaFormat::esClaveProdServ($valor)
            ? null
            : self::digitos('formato-clave-prod-serv', $valor, $etiqueta, $campo, ClaveNumericaFormat::DIGITOS_CLAVE_PROD_SERV);
    }

    private static function digitos(string $code, string $valor, string $etiqueta, string $campo, int $digitos): Violation
    {
        return new Violation($code, sprintf(
            '%s "%s" no es válido: debe tener exactamente %d dígitos. Si se capturó como número, revisa que no se hayan perdido los ceros a la izquierda.',
            $etiqueta, $valor, $digitos
        ), $campo);
    }
}
