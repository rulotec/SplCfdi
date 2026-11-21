<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Support;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Addendas\{Addenda, CampoAddenda, DatosAddenda, NivelCampo, TipoCampo};
use SplCfdi\Domain\Models\{Comprobante, ComprobanteCalculado};

/** Addenda mínima, sin XSD, para probar el mecanismo sin depender de las de clientes reales. */
final class AddendaDePrueba implements Addenda
{
    public const NAMESPACE = 'urn:prueba:addenda';

    public function __construct(private readonly ?string $esquema = null) {}

    public function clave(): string
    {
        return 'PRUEBA';
    }

    public function nombre(): string
    {
        return 'De prueba';
    }

    public function namespace(): string
    {
        return self::NAMESPACE;
    }

    public function campos(): array
    {
        return [
            new CampoAddenda('codigo', 'Código', NivelCampo::Cliente, requerido: true, patron: '[0-9]{3}', ayuda: 'tres dígitos'),
            new CampoAddenda('tipo', 'Tipo', NivelCampo::Comprobante, TipoCampo::Opcion, opciones: ['A' => 'Alfa', 'B' => 'Beta']),
            new CampoAddenda('cantidad', 'Cantidad', NivelCampo::Comprobante, TipoCampo::Entero),
            new CampoAddenda('monto', 'Monto', NivelCampo::Comprobante, TipoCampo::Decimal),
            new CampoAddenda('nombre', 'Nombre', NivelCampo::Comprobante, longitudMinima: 2, longitudMaxima: 5),
            new CampoAddenda('orden', 'Orden', NivelCampo::Concepto, requerido: true),
        ];
    }

    public function porDefecto(Comprobante $comprobante): DatosAddenda
    {
        return new DatosAddenda($this->clave());
    }

    public function esquema(): ?string
    {
        return $this->esquema;
    }

    public function reglas(ComprobanteCalculado $calculado, DatosAddenda $datos): array
    {
        return [];
    }

    public function crear(DOMDocument $dom, ComprobanteCalculado $calculado, DatosAddenda $datos): DOMElement
    {
        $nodo = $dom->createElementNS(self::NAMESPACE, 'p:Datos');
        $nodo->setAttribute('codigo', $datos->valor('codigo') ?? '');

        return $nodo;
    }
}
