<?php

require_once 'vendor/autoload.php';

use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Models\Emisor;
use SplCfdi\Domain\Models\ImpuestoTrasladado;
use SplCfdi\Domain\Models\Receptor;

use SplCfdi\Domain\Services\ImpuestoTrasladadoAggregator;
use SplCfdi\Domain\Services\InvoiceCalculator;
use SplCfdi\Infrastructure\Cfdi\SimpleCfdiXmlGenerator;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\Moneda;

$emisor = new Emisor(
    rfc: 'AAA010101AAA',
    nombre: 'EMPRESA DE PRUEBA',
    regimenFiscal: '601'
);

$receptor = new Receptor(
    rfc: 'XAXX010101000',
    nombre: 'PUBLICO EN GENERAL',
    domicilioFiscalReceptor: '62000',
    regimenFiscalReceptor: '616',
    usoCFDI: 'S01'
);


$concepto = new Concepto(
    claveProdServ: '81112100',
    cantidad: '1.00',
    claveUnidad: 'E48',
    unidad: 'Servicio',
    descripcion: 'Servicio de consultoría',
    valorUnitario: '1000.00',
    importe: '1000.00',
    objetoImp: '02',
    impuestosTrasladados: [new ImpuestoTrasladado(
        impuesto: '002',
        tipoFactor: 'Tasa',
        tasaOCuota: '0.160000',
    )]
);

$comprobante = new Comprobante(
    version: '4.0',
    fecha: '2026-09-17T17:00:00',
    moneda: Moneda::mxn(),
    tipoDeComprobante: 'I',
    exportacion: '01',
    lugarExpedicion: '62000',
    emisor: $emisor,
    receptor: $receptor,
    conceptos: [$concepto],
    serie: 'A',
    folio: '1',
    metodoPago: 'PUE',
    formaPago: '03'
);

$decimalConfiguration = new DecimalConfiguration(
    calculationScale: 6,
    maximumScale: 6
);

$decimalCalculator = new BcMathDecimalMath($decimalConfiguration);

$aggregator = new ImpuestoTrasladadoAggregator(
    $decimalCalculator
);

$calculator = new InvoiceCalculator(
    $decimalCalculator,
    $aggregator
);

$comprobanteCalculado = $calculator->calculate($comprobante);

$generator = new SimpleCfdiXmlGenerator();
$xmlCfdi = $generator->generate($comprobanteCalculado);

echo '<pre>';
echo htmlspecialchars(
    $xmlCfdi,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
    );
echo '</pre>';

$document = new DOMDocument();
$esValido = $document->loadXML($xmlCfdi);
if ($esValido) {
    echo "Es válido!!";
} else {
    throw new RuntimeException('El XML no está bien formado');
}

echo '<pre>';
var_dump($comprobanteCalculado->importes);
echo '</pre>';
