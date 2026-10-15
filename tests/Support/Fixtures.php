<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Support;

use DOMDocument;
use DateTimeImmutable;
use DateTimeZone;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Models\{Comprobante, Concepto, Emisor, ImpuestoRetenido, ImpuestoTrasladado, Moneda, Receptor, TipoFactor};
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Models\InformacionGlobal;

final class Fixtures
{
    /** @param ImpuestoTrasladado[] $traslados @param ImpuestoRetenido[] $retenciones */
    private static function build(
        string $descripcion,
        string $valorUnitario,
        string $objetoImp,
        array $traslados,
        string $cantidad = '1',
        ?string $descuento = null,
        array $retenciones = [],
    ): Concepto {
        return new Concepto(
            claveProdServ: '81112100', cantidad: $cantidad, claveUnidad: 'E48', unidad: 'Servicio',
            descripcion: $descripcion, valorUnitario: $valorUnitario, objetoImp: $objetoImp,
            impuestosTrasladados: $traslados, descuento: $descuento, impuestosRetenidos: $retenciones,
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
        return self::build(
            'Prueba', $valorUnitario, '02',
            [new ImpuestoTrasladado('002', TipoFactor::Tasa, $tasa)],
            $cantidad, $descuento, $retenciones
        );
    }

    public static function conceptoExento(string $valorUnitario): Concepto
    {
        return self::build('Prueba exenta', $valorUnitario, '02', [new ImpuestoTrasladado('002', TipoFactor::Exento)]);
    }

    public static function conceptoSinImpuestos(string $valorUnitario): Concepto
    {
        return self::build('Prueba sin impuestos', $valorUnitario, '01', []);
    }

    /** Para probar combinaciones de ObjetoImp / traslados que los demás helpers no producen. */
    public static function conceptoConObjetoImp(string $valorUnitario, string $objetoImp, array $traslados = []): Concepto
    {
        return self::build('Prueba objeto', $valorUnitario, $objetoImp, $traslados);
    }

    public static function receptorPublicoEnGeneral(): Receptor
    {
        return new Receptor('XAXX010101000', 'PUBLICO EN GENERAL', '62000', '616', 'S01');
    }

    public static function receptorNominativo(): Receptor
    {
        return new Receptor('EKU9003173C9', 'ESCUELA KEMPER URGATE', '42501', '601', 'G03');
    }

    public static function informacionGlobal(): InformacionGlobal
    {
        return new InformacionGlobal('01', '10', 2026);
    }

    /** Una hora después de la Fecha de los comprobantes de prueba: dentro de la ventana de 72 horas. */
    public static function clock(): FixedClock
    {
        return new FixedClock(new DateTimeImmutable('2026-10-01T13:00:00', new DateTimeZone('America/Mexico_City')));
    }

    /** Público en general con InformacionGlobal, PUE y forma de pago: un CFDI global válido. */
    public static function comprobante(array $conceptos, ?Moneda $moneda = null, ?DateTimeImmutable $fecha = null): Comprobante
    {
        return self::comprobanteCon(
            $conceptos, self::receptorPublicoEnGeneral(), self::informacionGlobal(), $moneda, $fecha,
            metodoPago: 'PUE', formaPago: '01',
        );
    }

    /** Receptor identificado, sin InformacionGlobal. */
    public static function comprobanteNominativo(array $conceptos): Comprobante
    {
        return self::comprobanteCon($conceptos, self::receptorNominativo(), null);
    }

    /** @param Concepto[] $conceptos */
    public static function comprobanteCon(
        array $conceptos,
        Receptor $receptor,
        ?InformacionGlobal $informacionGlobal,
        ?Moneda $moneda = null,
        ?DateTimeImmutable $fecha = null,
        string $tipoDeComprobante = 'I',
        string $lugarExpedicion = '62000',
        ?string $metodoPago = null,
        ?string $formaPago = null,
        ?Emisor $emisor = null,
    ): Comprobante {
        return new Comprobante(
            version: '4.0',
            fecha: $fecha ?? new DateTimeImmutable('2026-10-01T12:00:00'),
            moneda: $moneda ?? Moneda::mxn(),
            tipoDeComprobante: $tipoDeComprobante,
            exportacion: '01',
            lugarExpedicion: $lugarExpedicion,
            emisor: $emisor ?? new Emisor('AAA010101AAA', 'EMPRESA DE PRUEBA', '601'),
            receptor: $receptor,
            conceptos: $conceptos,
            metodoPago: $metodoPago,
            formaPago: $formaPago,
            informacionGlobal: $informacionGlobal,
        );
    }

    public static function calculado(Comprobante $comprobante): ComprobanteCalculado
    {
        return CfdiFactory::createCalculator()->calculate($comprobante);
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
