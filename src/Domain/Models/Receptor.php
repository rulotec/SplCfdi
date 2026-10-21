<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

use SplCfdi\Domain\Support\ClaveNumericaFormat;

final class Receptor
{
    public const RFC_PUBLICO_GENERAL = 'XAXX010101000';
    public const RFC_EXTRANJERO_GENERICO = 'XEXX010101000';
    public const NOMBRE_PUBLICO_GENERAL = 'PUBLICO EN GENERAL';

    public function __construct(
        public readonly string $rfc,
        public readonly string $nombre,
        public readonly string $domicilioFiscalReceptor,
        public readonly string $regimenFiscalReceptor,
        public readonly string $usoCFDI
    ) {
        ClaveNumericaFormat::assertCodigoPostal($domicilioFiscalReceptor, 'DomicilioFiscalReceptor');
    }

    public function esRfcGenerico(): bool
    {
        return in_array($this->rfc, [self::RFC_PUBLICO_GENERAL, self::RFC_EXTRANJERO_GENERICO], true);
    }

    /** Cuando es verdadero, el SAT exige el nodo InformacionGlobal. */
    public function esPublicoEnGeneral(): bool
    {
        return $this->rfc === self::RFC_PUBLICO_GENERAL && $this->nombre === self::NOMBRE_PUBLICO_GENERAL;
    }
}
