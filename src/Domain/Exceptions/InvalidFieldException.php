<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Exceptions;

/**
 * Un dato de entrada con forma inválida, detectado al construir los modelos.
 * Lleva el campo y el valor para que el host pueda señalarlo en su formulario.
 */
final class InvalidFieldException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $campo,
        public readonly ?string $valor,
        string $message,
    ) {
        parent::__construct($message);
    }
}
