<?php

declare(strict_types=1);

namespace SplCfdi\Application\AddendaForm;

/** A value the host already knows for an addenda field, and where it comes from. */
final class DefaultValue
{
    /** @param string|null $source shown to the user, e.g. "Cuenta › Código de proveedor" or "Configuración" */
    public function __construct(
        public readonly string $value,
        public readonly ?string $source = null,
    ) {}
}
