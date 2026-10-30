<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Addendas;

enum TipoCampo: string
{
    case Texto = 'texto';
    case Entero = 'entero';
    case Decimal = 'decimal';

    /** Uno de CampoAddenda::$opciones. */
    case Opcion = 'opcion';
}
