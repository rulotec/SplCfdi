<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Addendas;

use DOMDocument;
use DOMElement;
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Rules\Violation;

/**
 * Una addenda de cliente (Volkswagen PSV, Stellantis PPY…). Para agregar una: implementa esta interfaz
 * (o extiende Addendas\AddendaBase), pon su XSD en resources/addendas y regístrala en
 * AddendaRegistry::standard(). Un host puede registrar las suyas con AddendaRegistry::con().
 */
interface Addenda
{
    /** Identificador estable que guarda el host, p. ej. "PSV". */
    public function clave(): string;

    /** Para mostrar al usuario, p. ej. "Volkswagen de México – Servicios (PSV)". */
    public function nombre(): string;

    /** Namespace del nodo raíz de la addenda: con él se reconoce en un XML. */
    public function namespace(): string;

    /** @return CampoAddenda[] lo que el host debe capturar */
    public function campos(): array;

    /** Ruta del XSD con que se valida el nodo de la addenda; null si no hay. */
    public function esquema(): ?string;

    /**
     * Reglas propias de la addenda. Las de cada campo (requerido, longitud, patrón, opciones) ya las revisa
     * RuleAddenda a partir de campos().
     *
     * @return Violation[]
     */
    public function reglas(ComprobanteCalculado $calculado, DatosAddenda $datos): array;

    /**
     * El nodo raíz de la addenda (lo que va dentro de cfdi:Addenda). No debe lanzar con datos incompletos:
     * en un borrador se genera aunque haya violaciones.
     */
    public function crear(DOMDocument $dom, ComprobanteCalculado $calculado, DatosAddenda $datos): DOMElement;
}
