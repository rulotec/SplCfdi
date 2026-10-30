<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Addendas;

use SplCfdi\Domain\Contracts\CfdiXmlGenerator;
use SplCfdi\Domain\Models\ComprobanteCalculado;

/** Decora un generador de CFDI: al XML le agrega la addenda del comprobante, si tiene. */
final class AddendaXmlGenerator implements CfdiXmlGenerator
{
    public function __construct(
        private readonly CfdiXmlGenerator $generator,
        private readonly AddendaWriter $writer,
    ) {}

    public function generate(ComprobanteCalculado $comprobante): string
    {
        return $this->writer->write($this->generator->generate($comprobante), $comprobante, true);
    }
}
