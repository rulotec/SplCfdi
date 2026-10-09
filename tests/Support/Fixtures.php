<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Support;

use DOMDocument;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Models\{Comprobante, Concepto, Emisor, ImpuestoRetenido, ImpuestoTrasladado, Moneda, Receptor, TipoFactor};
use SplCfdi\Domain\Services\{ImpuestoRetenidoAggregator, ImpuestoTrasladadoAggregator, InvoiceCalculator};
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;

final class Fixtures
{
    public static function calculator(?DecimalConfiguration $config = null): InvoiceCalculator
    {
        $config ??= DecimalConfiguration::sat();
        $math = new BcMathDecimalMath($config);

        return new InvoiceCalculator(
            $math,
            new ImpuestoTrasladadoAggregator($math),
            new ImpuestoRetenidoAggregator($math),
            $config,
        );
    }

    /** @param ImpuestoRetenido[] $retenciones */
    public static function concepto(
        string $valorUnitario,
        string $tasa = '0.160000',
        string $cantidad = '1',
        ?string $descuento = null,
        array $retenciones = [],
    ): Concepto {
        return new Concepto(
            claveProdServ: '81112100', cantidad: $cantidad, claveUnidad: 'E48', unidad: 'Servicio',
            descripcion: 'Prueba', valorUnitario: $valorUnitario, objetoImp: '02',
            impuestosTrasladados: [new ImpuestoTrasladado('002', TipoFactor::Tasa, $tasa)],
            descuento: $descuento,
            impuestosRetenidos: $retenciones,
        );
    }

    public static function conceptoExento(string $valorUnitario): Concepto
    {
        return new Concepto(
            claveProdServ: '81112100', cantidad: '1', claveUnidad: 'E48', unidad: 'Servicio',
            descripcion: 'Prueba exenta', valorUnitario: $valorUnitario, objetoImp: '02',
            impuestosTrasladados: [new ImpuestoTrasladado('002', TipoFactor::Exento)],
        );
    }

    public static function conceptoSinImpuestos(string $valorUnitario): Concepto
    {
        return new Concepto(
            claveProdServ: '81112100', cantidad: '1', claveUnidad: 'E48', unidad: 'Servicio',
            descripcion: 'Prueba sin impuestos', valorUnitario: $valorUnitario, objetoImp: '01',
            impuestosTrasladados: [],
        );
    }

    /** @param Concepto[] $conceptos */
    public static function comprobante(array $conceptos, ?Moneda $moneda = null): Comprobante
    {
        return new Comprobante(
            version: '4.0', fecha: '2026-10-01T12:00:00', moneda: $moneda ?? Moneda::mxn(),
            tipoDeComprobante: 'I', exportacion: '01', lugarExpedicion: '62000',
            emisor: new Emisor('AAA010101AAA', 'EMPRESA DE PRUEBA', '601'),
            receptor: new Receptor('XAXX010101000', 'PUBLICO EN GENERAL', '62000', '616', 'S01'),
            conceptos: $conceptos,
        );
    }

    /** Simula lo que hará el Sealer, para poder validar contra el XSD antes de que exista. */
    public static function withSealPlaceholders(string $xml): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $root = $dom->documentElement;
        $root->setAttribute('Sello', 'AAAA');
        $root->setAttribute('NoCertificado', '30001000000500003456');
        $root->setAttribute('Certificado', 'AAAA');

        return $dom->saveXML();
    }
}
