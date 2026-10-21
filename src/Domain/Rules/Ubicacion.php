<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Models\ConceptoCalculado;

/** Cómo las reglas nombran un concepto: igual en el mensaje y en Violation::$campo. */
final class Ubicacion
{
    /** Para el mensaje: 'Concepto 2 ("Servicio de soporte")'. $indice empieza en 0. */
    public static function concepto(int $indice, ConceptoCalculado $concepto): string
    {
        return sprintf('Concepto %d ("%s")', $indice + 1, $concepto->concepto->descripcion);
    }

    /** Para Violation::$campo: 'Concepto[2].ObjetoImp'. $indice empieza en 0. */
    public static function campoConcepto(int $indice, string $atributo): string
    {
        return sprintf('Concepto[%d].%s', $indice + 1, $atributo);
    }
}
