<?php

declare(strict_types=1);

namespace SplCfdi\Application;

use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Rules\Violation;

/**
 * Resultado de CfdiService::generate() y validate(). Nunca se lanza una excepción por datos inválidos:
 * las violaciones vienen aquí, todas juntas, listas para mostrarse al usuario final.
 */
final class CfdiResult
{
    /**
     * @param Violation[]               $violations vacío si el CFDI es válido
     * @param ComprobanteCalculado|null $calculado  null si los datos tienen errores de formato y no se pudo calcular
     * @param string|null               $xml        solo desde generate() y sin violaciones
     */
    public function __construct(
        public readonly array $violations,
        public readonly ?ComprobanteCalculado $calculado = null,
        public readonly ?string $xml = null,
    ) {}

    public function isValid(): bool
    {
        return $this->violations === [];
    }
}
