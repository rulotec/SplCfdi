<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

final class Violation
{
    public function __construct(
        public readonly string $code,           // identificador estable de la regla, p. ej. "fecha-vencida"
        public readonly string $message,        // texto para el usuario final
        public readonly ?string $campo = null,  // ruta del atributo en el XML, p. ej. "Concepto[2].ObjetoImp"
    ) {}
}
