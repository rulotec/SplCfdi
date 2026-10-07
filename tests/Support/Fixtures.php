<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Support;

use DOMDocument;
use SplCfdi\Domain\Models\{Comprobante, Concepto, Emisor, ImpuestoTrasladado, Moneda, Receptor};

final class Fixtures
{
    public static function concepto(string $importe, string $tasa = '0.160000'): Concepto
    {
        return new Concepto(
            claveProdServ: '81112100', cantidad: '1', claveUnidad: 'E48', unidad: 'Servicio',
            descripcion: 'Prueba', valorUnitario: $importe, importe: $importe, objetoImp: '02',
            impuestosTrasladados: [new ImpuestoTrasladado('002', 'Tasa', $tasa)],
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
