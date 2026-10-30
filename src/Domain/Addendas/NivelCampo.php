<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Addendas;

/** Dónde vive el dato en el host: le dice dónde capturarlo y guardarlo. */
enum NivelCampo: string
{
    /** Fijo por cliente (receptor): código de proveedor que el cliente asignó, CLABE, división… */
    case Cliente = 'cliente';

    /** Cambia en cada factura: solicitante, remisión… */
    case Comprobante = 'comprobante';

    /** Cambia en cada concepto: orden de compra, posición, número de material… */
    case Concepto = 'concepto';
}
