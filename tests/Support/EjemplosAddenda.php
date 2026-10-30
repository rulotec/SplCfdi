<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use SplCfdi\Addendas\VolkswagenPsv;
use SplCfdi\Domain\Addendas\{AddendaRegistry, DatosAddenda};
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{Comprobante, Concepto, Emisor, ImpuestoTrasladado, Receptor, TipoFactor};
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;

/**
 * Los CFDI de tests/Fixtures/addendas, reconstruidos como Comprobante + DatosAddenda: generar con ellos debe
 * producir la misma addenda que el ejemplo. Son CFDI reales del generador anterior con los datos
 * sustituidos por ficticios (RFC de prueba del SAT, importes, contactos, códigos); la estructura y sus
 * rarezas son las originales.
 */
final class EjemplosAddenda
{
    public const DIR = __DIR__ . '/../Fixtures/addendas';

    /**
     * Archivo => [Comprobante, diferencias intencionales con el ejemplo (texto del ejemplo => texto generado),
     * addendas con que se genera].
     *
     * @return array<string, array{Comprobante, array<string, string>, AddendaRegistry}>
     */
    public static function todos(): array
    {
        $math = new BcMathDecimalMath(DecimalConfiguration::sat());
        $standard = AddendaRegistry::standard($math);

        return [
            // El generador anterior repartía las notas en nodos de 25 caracteres: así se configura esa rareza.
            'VOLKSWAGEN.xml' => [self::volkswagen(), [
                // Se usan los decimales del XSD: cantidadMaterial admite 4 y precioUnitario 5.
                'cantidadMaterial="1.000000"' => 'cantidadMaterial="1.0000"',
                'precioUnitario="5000.0000"' => 'precioUnitario="5000.00000"',
            ], $standard->con(new VolkswagenPsv($math, 25))],
            'Stellantis.xml' => [self::stellantis(), [
                // El XSD admite 2 decimales en montoTotal y en otrosCargos/monto.
                'montoTotal="3770.000000"' => 'montoTotal="3770.00"',
                'monto="520.000000"' => 'monto="520.00"',
            ], $standard],
            'AMERICAN_AXLE.xml' => [self::americanAxle(), [
                // El ejemplo trae el texto con doble codificación UTF-8.
                'RevisiÃ³n' => 'Revisión',
            ], $standard],
        ];
    }

    /** Una hora después de la Fecha: dentro de la ventana de 72 horas. */
    public static function clock(Comprobante $comprobante): FixedClock
    {
        return new FixedClock($comprobante->fecha->modify('+1 hour'));
    }

    public static function xml(string $archivo): string
    {
        return (string) file_get_contents(self::DIR . "/$archivo");
    }

    /** El nodo dentro de cfdi:Addenda, en forma canónica (C14N exclusiva: sin namespaces que no se usan). */
    public static function addendaCanonica(string $xml): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        foreach (LibXmlSchemaValidator::addendas($dom) as $contenedor) {
            foreach ($contenedor->childNodes as $nodo) {
                if ($nodo instanceof DOMElement) {
                    return (string) $nodo->C14N(true, false);
                }
            }
        }

        return '';
    }

    /** El XML sin su cfdi:Addenda, como quedaría antes de agregarla. */
    public static function sinAddenda(string $xml): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        foreach (LibXmlSchemaValidator::addendas($dom) as $addenda) {
            $addenda->parentNode?->removeChild($addenda);
        }

        return (string) $dom->saveXML();
    }

    public static function volkswagen(): Comprobante
    {
        return self::comprobante(
            '2026-10-06T16:18:11', '1003',
            new Receptor('IIA040805DZ4', 'INDISTRIA ILUMINADORA DE ALMACENES', '62661', '601', 'G03'),
            [self::concepto('86101705', '1.000000', 'MON', 'MON', 'Servicio de prueba año 1', '5000.000000')],
            new DatosAddenda('PSV', [
                'division' => 'VW',
                'codigoProveedor' => '1234567890',
                'correoContacto' => 'contacto@example.com',
                'codigoImpuesto' => '1A',
                'tipoMoneda' => 'MXP',
                'remision' => 'REM01',
                'solicitanteNombre' => 'SOLICITANTE DE PRUEBA',
                'solicitanteCorreo' => 'solicitante@example.com',
                'ordenCompra' => '9000000001',
            ], [
                ['posicion' => '1', 'nota' => "SERVICIO DE PRUEBA \nPeriodo Enero 2026 - Diciembre 2026 Mes de Septiembre 2026"],
            ]),
        );
    }

    public static function stellantis(): Comprobante
    {
        return self::comprobante(
            '2026-10-23T16:18:03', '1002',
            new Receptor('URE180429TM6', 'UNIVERSIDAD ROBOTICA ESPAÑOLA', '86991', '601', 'G03'),
            [self::concepto('84111506', '1000.000000', 'H87', 'EA', 'SERVICIO DE PRUEBA', '3.250000')],
            new DatosAddenda('PPY', [
                'codigoProveedor' => '12345',
                'referenciaProveedor' => 'REF01',
                'destinoCodigo' => '1234',
                'destinoNombre' => 'PLANTA DE PRUEBA',
            ], [
                ['ordenCompra' => '90000001', 'releaseRequisicion' => '90000001', 'ammendment' => '00001'],
            ]),
        );
    }

    public static function americanAxle(): Comprobante
    {
        return self::comprobante(
            '2026-10-01T18:30:02', '1001',
            new Receptor('XIA190128J61', 'XENON INDUSTRIAL ARTICLES', '76343', '601', 'G03'),
            [
                self::concepto('84111506', '120.000000', 'H87', 'SET', 'SERVICIO DE PRUEBA A', '2.500000'),
                self::concepto('84111506', '30.000000', 'H87', 'SET', 'SERVICIO DE PRUEBA B', '1.250000'),
            ],
            new DatosAddenda('IEX', [
                'clabe' => '11111',
                'nombreBanco' => '22222',
                'organizacionOperacional' => 'ABC',
                'requisitorNombre' => 'Persona de Prueba',
                'requisitorCorreo' => 'requisitor@example.com',
                // De nivel Concepto, pero iguales en toda la factura: se dan una vez.
                'ordenCompra' => '900001',
                'requisicion' => '800001',
                'referenciaProveedor' => '700001',
            ], [
                ['numero' => '10', 'nota' => 'Servicio de prueba de septiembre.'],
                ['numero' => '20', 'nota' => 'Revisión de prueba de septiembre.'],
            ]),
        );
    }

    /** @param Concepto[] $conceptos */
    private static function comprobante(string $fecha, string $folio, Receptor $receptor, array $conceptos, DatosAddenda $addenda): Comprobante
    {
        return new Comprobante(
            version: '4.0',
            fecha: new DateTimeImmutable($fecha, new DateTimeZone('America/Mexico_City')),
            moneda: 'MXN', tipoDeComprobante: 'I', exportacion: '01', lugarExpedicion: '42501',
            emisor: new Emisor('EKU9003173C9', 'ESCUELA KEMPER URGATE', '601'),
            receptor: $receptor, conceptos: $conceptos,
            folio: $folio, metodoPago: 'PPD', formaPago: '99', tipoCambio: '1',
            addenda: $addenda,
        );
    }

    private static function concepto(string $clave, string $cantidad, string $claveUnidad, string $unidad, string $descripcion, string $valorUnitario): Concepto
    {
        return new Concepto(
            claveProdServ: $clave, cantidad: $cantidad, claveUnidad: $claveUnidad, unidad: $unidad,
            descripcion: $descripcion, valorUnitario: $valorUnitario, objetoImp: '02',
            impuestosTrasladados: [new ImpuestoTrasladado('002', TipoFactor::Tasa, '0.160000')],
        );
    }
}
