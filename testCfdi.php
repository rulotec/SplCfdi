<?php

require_once 'Domain/Models/Emisor.php';
require_once 'Domain/Models/Receptor.php';
require_once 'Domain/Models/Concepto.php';
require_once 'Domain/Models/Comprobante.php';
require_once 'Domain/Models/ImportesComprobante.php';
require_once 'Domain/Models/ImpuestosComprobante.php';
require_once 'Domain/Models/ImpuestoTrasladado.php';
require_once 'Domain/Models/ImpuestoTrasladadoCalculado.php';
require_once 'Domain/Models/ResultadoCalculoComprobante.php';
require_once 'Domain/Configuration/DecimalConfiguration.php';
require_once 'Domain/Contracts/CfdiXmlGenerator.php';
require_once 'Domain/Services/InvoiceCalculator.php';
require_once 'Domain/Services/ImpuestoTrasladadoAggregator.php';
require_once 'Domain/Services/DecimalCalculator.php';
require_once 'Infrastructure/Cfdi/SimpleCfdiXmlGenerator.php';


use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Models\Concepto;
use SplCfdi\Domain\Models\Emisor;
use SplCfdi\Domain\Models\ImpuestoTrasladado;
use SplCfdi\Domain\Models\Receptor;
use SplCfdi\Domain\Services\DecimalCalculator;
use SplCfdi\Domain\Services\ImpuestoTrasladadoAggregator;
use SplCfdi\Domain\Services\InvoiceCalculator;
use SplCfdi\Infrastructure\Cfdi\SimpleCfdiXmlGenerator;
use SplCfdi\Domain\Configuration\DecimalConfiguration;

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
    moneda: 'MXN',
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

$decimalCalculator = new DecimalCalculator($decimalConfiguration);

$aggregator = new ImpuestoTrasladadoAggregator(
    $decimalCalculator
);

$calculator = new InvoiceCalculator(
    $decimalCalculator,
    $aggregator
);

$resultado = $calculator->calculate(
    $comprobante
);

$comprobante->setImportesYImpuestos(
    $resultado->importes,
    $resultado->impuestos
);

$generator = new SimpleCfdiXmlGenerator();
$xmlCfdi = $generator->generate($comprobante);

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
var_dump($comprobante->getImportes());
echo '</pre>';
