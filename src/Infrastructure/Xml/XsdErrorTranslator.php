<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Xml;

use DOMDocument;
use DOMElement;
use LibXMLError;
use SplCfdi\Domain\Rules\Violation;

/**
 * Traduce los errores de libxml al validar contra el XSD a Violations en español, con la misma ruta
 * (`campo`) que usan las reglas: "Concepto[2].Traslado[1].TasaOCuota", "Emisor.Rfc", "Moneda".
 *
 * Supone un documento con un elemento por línea (LibXmlSchemaValidator lo reformatea): así la línea
 * del error identifica el elemento.
 */
final class XsdErrorTranslator
{
    /** Atributo => catálogo, para los mensajes de enumeración (libxml no dice qué tipo falló). */
    private const CATALOGOS = [
        'ClaveProdServ' => 'c_ClaveProdServ',
        'ClaveUnidad' => 'c_ClaveUnidad',
        'LugarExpedicion' => 'c_CodigoPostal',
        'Exportacion' => 'c_Exportacion',
        'FormaPago' => 'c_FormaPago',
        'Impuesto' => 'c_Impuesto',
        'Meses' => 'c_Meses',
        'MetodoPago' => 'c_MetodoPago',
        'Moneda' => 'c_Moneda',
        'ObjetoImp' => 'c_ObjetoImp',
        'ResidenciaFiscal' => 'c_Pais',
        'Periodicidad' => 'c_Periodicidad',
        'RegimenFiscal' => 'c_RegimenFiscal',
        'RegimenFiscalReceptor' => 'c_RegimenFiscal',
        'TipoDeComprobante' => 'c_TipoDeComprobante',
        'TipoFactor' => 'c_TipoFactor',
        'TipoRelacion' => 'c_TipoRelacion',
        'UsoCFDI' => 'c_UsoCFDI',
    ];

    /** Los patrones del XSD son ilegibles para el usuario: se explica la forma esperada. */
    private const FORMAS = [
        'Rfc' => 'un RFC de 12 (persona moral) o 13 (persona física) caracteres',
        'DomicilioFiscalReceptor' => 'un código postal de cinco dígitos',
        'LugarExpedicion' => 'un código postal de cinco dígitos',
        'ClaveProdServ' => 'una clave de ocho dígitos',
        'UUID' => 'un folio fiscal (UUID) como 6F9A1B2C-1234-4ABC-9DEF-0123456789AB',
        'NoCertificado' => 'veinte dígitos',
        'Confirmacion' => 'cinco letras o dígitos',
        'TipoCambio' => 'un número con hasta seis decimales',
    ];

    /** Contenedores que no aparecen en `campo`: "Concepto[1].Traslado[1]", no "Conceptos.Concepto[1]…". */
    private const CONTENEDORES = ['Conceptos', 'Traslados', 'Retenciones'];

    /** Se numeran siempre, aunque haya uno solo, como en las reglas. */
    private const NUMERADOS = ['Concepto', 'Traslado', 'Retencion', 'CfdiRelacionados', 'CfdiRelacionado'];

    /**
     * @param LibXMLError[] $errors
     *
     * @return Violation[]
     */
    public function translate(array $errors, DOMDocument $dom): array
    {
        $porLinea = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            $porLinea[$element->getLineNo()] ??= $element;
        }

        $violaciones = [];
        foreach ($errors as $error) {
            $violacion = $this->translateOne(trim($error->message), $porLinea[$error->line] ?? null);
            // libxml reporta varias facetas del mismo atributo ("pattern" y luego "not a valid value"):
            // basta la primera.
            $violaciones[$violacion->campo ?? $violacion->message] ??= $violacion;
        }

        return array_values($violaciones);
    }

    private function translateOne(string $message, ?DOMElement $element): Violation
    {
        $ruta = $element === null ? [] : $this->ruta($element);
        $lugar = $element === null ? 'Comprobante' : $this->lugar($element);
        $campoElemento = $ruta === [] ? null : implode('.', $ruta);

        // Element '{ns}Concepto', attribute 'Cantidad': [facet '…'] …
        if (preg_match("/^Element '[^']*', attribute '([^']+)': (.*)$/s", $message, $m) === 1) {
            return $this->attributeError($m[1], $m[2], $lugar, implode('.', [...$ruta, $m[1]]));
        }

        // Element '{ns}Receptor': …
        $detalle = (string) preg_replace("/^Element '[^']*': /", '', $message);

        if (preg_match("/^The attribute '([^']+)' is required but missing/", $detalle, $m) === 1) {
            return new Violation('xsd-requerido', "$lugar: falta el atributo obligatorio {$m[1]}.", implode('.', [...$ruta, $m[1]]));
        }
        if (preg_match("/^The attribute '([^']+)' is not allowed/", $detalle, $m) === 1) {
            return new Violation('xsd-no-permitido', "$lugar: el atributo {$m[1]} no está permitido.", implode('.', [...$ruta, $m[1]]));
        }
        if (str_starts_with($detalle, 'This element is not expected')) {
            return new Violation('xsd-estructura', sprintf(
                '%s: el nodo no va en esta posición%s.', $lugar, $this->esperados($detalle, '; se esperaba ')
            ), $campoElemento);
        }
        if (str_starts_with($detalle, 'Missing child element')) {
            return new Violation('xsd-estructura', sprintf(
                '%s: falta un nodo obligatorio%s.', $lugar, $this->esperados($detalle, ': ')
            ), $campoElemento);
        }
        if (str_starts_with($detalle, 'No matching global declaration available for the validation root')) {
            return new Violation('xsd-estructura', 'El XML no es un CFDI 4.0: el nodo raíz debe ser cfdi:Comprobante con el namespace http://www.sat.gob.mx/cfd/4.');
        }
        if (str_starts_with($detalle, 'Character content other than whitespace is not allowed')) {
            return new Violation('xsd-estructura', "$lugar: el nodo no admite texto, solo atributos y nodos.", $campoElemento);
        }

        return new Violation('xsd', sprintf('%s: %s', $lugar, $this->sinNamespaces($detalle)), $campoElemento);
    }

    private function attributeError(string $atributo, string $detalle, string $lugar, string $campo): Violation
    {
        $valor = preg_match("/The value '(.*?)' /s", $detalle, $v) === 1 ? $v[1] : null;
        if ($valor === null && preg_match("/^'(.*?)' is not a valid value/s", $detalle, $v) === 1) {
            $valor = $v[1];
        }
        $prefijo = $valor === null ? "$lugar: $atributo" : sprintf('%s: %s "%s"', $lugar, $atributo, $valor);

        if (str_contains($detalle, "[facet 'enumeration']")) {
            $catalogo = self::CATALOGOS[$atributo] ?? null;

            return new Violation('xsd-catalogo', $catalogo === null
                ? "$prefijo no es uno de los valores permitidos."
                : "$prefijo no existe en el catálogo $catalogo.", $campo);
        }
        if (str_contains($detalle, "[facet 'pattern']")) {
            $forma = self::FORMAS[$atributo] ?? null;

            return new Violation('xsd-patron', $forma === null
                ? "$prefijo no tiene el formato que exige el SAT (revisa caracteres no permitidos o espacios)."
                : "$prefijo no tiene el formato requerido: se espera $forma.", $campo);
        }
        if (preg_match("/\\[facet '(minInclusive|minExclusive|maxInclusive|maxExclusive)'\\].*\\('([^']*)'\\)/s", $detalle, $f) === 1) {
            $texto = [
                'minInclusive' => 'debe ser al menos %s',
                'minExclusive' => 'debe ser mayor que %s',
                'maxInclusive' => 'no puede ser mayor que %s',
                'maxExclusive' => 'debe ser menor que %s',
            ][$f[1]];
            $codigo = str_starts_with($f[1], 'min') ? 'xsd-minimo' : 'xsd-maximo';

            return new Violation($codigo, sprintf("$prefijo $texto.", $f[2]), $campo);
        }
        if (preg_match("/\\[facet '(maxLength|minLength|length)'\\].*has a length of '(\\d+)'.*of '(\\d+)'/s", $detalle, $f) === 1) {
            $texto = [
                'maxLength' => 'tiene %s caracteres; el máximo es %s',
                'minLength' => 'tiene %s caracteres; el mínimo es %s',
                'length' => 'tiene %s caracteres; deben ser exactamente %s',
            ][$f[1]];

            $forma = isset(self::FORMAS[$atributo]) ? ': se espera ' . self::FORMAS[$atributo] : '';

            return new Violation('xsd-longitud', sprintf("$lugar: $atributo $texto%s.", $f[2], $f[3], $forma), $campo);
        }
        if (preg_match("/\\[facet 'fractionDigits'\\].*\\('(\\d+)'\\)/s", $detalle, $f) === 1) {
            return new Violation('xsd-decimales', "$prefijo tiene más de {$f[1]} decimales.", $campo);
        }
        if (preg_match("/^The attribute '[^']+' is not allowed/", $detalle) === 1) {
            return new Violation('xsd-no-permitido', "$lugar: el atributo $atributo no está permitido.", $campo);
        }
        if (preg_match("/is not a valid value of the (?:local )?(?:atomic|union) type(?: '([^']*)')?/", $this->sinNamespaces($detalle), $f) === 1) {
            $tipo = $f[1] ?? '';
            $explicacion = match (true) {
                str_contains($tipo, 'decimal'), str_starts_with($tipo, 't_Importe') => 'no es un número válido',
                str_contains($tipo, 'int') => 'no es un número entero válido',
                str_contains($tipo, 'dateTime'), str_contains($tipo, 't_FechaH') => 'no es una fecha y hora válida (AAAA-MM-DDThh:mm:ss)',
                default => 'no es un valor válido',
            };

            return new Violation('xsd-tipo', "$prefijo $explicacion.", $campo);
        }

        return new Violation('xsd', sprintf('%s: %s %s', $lugar, $atributo, $this->sinNamespaces($detalle)), $campo);
    }

    /**
     * Ruta de `campo` sin la raíz: ["Concepto[2]", "Traslado[1]"].
     *
     * @return string[]
     */
    private function ruta(DOMElement $element): array
    {
        $ruta = [];
        for ($e = $element; $e->parentNode instanceof DOMElement; $e = $e->parentNode) {
            if ($this->esContenedor($e)) {
                continue;
            }
            $indice = $this->indice($e);
            $ruta[] = $indice === null ? (string) $e->localName : sprintf('%s[%d]', $e->localName, $indice);
        }

        return array_reverse($ruta);
    }

    /** Para el mensaje: 'Concepto 2 ("Servicio"), Traslado 1', como Ubicacion en las reglas. */
    private function lugar(DOMElement $element): string
    {
        $partes = [];
        for ($e = $element; $e->parentNode instanceof DOMElement; $e = $e->parentNode) {
            if ($this->esContenedor($e)) {
                continue;
            }
            $indice = $this->indice($e);
            $parte = $indice === null ? (string) $e->localName : sprintf('%s %d', $e->localName, $indice);
            if ($e->localName === 'Concepto' && $e->hasAttribute('Descripcion')) {
                $parte .= sprintf(' ("%s")', $e->getAttribute('Descripcion'));
            }
            $partes[] = $parte;
        }

        return $partes === [] ? 'Comprobante' : implode(', ', array_reverse($partes));
    }

    /** Traslados/Retenciones del concepto o del resumen, y Conceptos; el Impuestos del concepto también. */
    private function esContenedor(DOMElement $e): bool
    {
        return in_array($e->localName, self::CONTENEDORES, true)
            || ($e->localName === 'Impuestos' && $e->parentNode instanceof DOMElement && $e->parentNode->localName === 'Concepto');
    }

    /** Posición (desde 1) entre los hermanos del mismo nombre; null si no se numera. */
    private function indice(DOMElement $e): ?int
    {
        $indice = 0;
        $total = 0;
        foreach ($e->parentNode->childNodes ?? [] as $hermano) {
            if ($hermano instanceof DOMElement && $hermano->localName === $e->localName) {
                $total++;
                if ($hermano === $e) {
                    $indice = $total;
                }
            }
        }

        return $total > 1 || in_array($e->localName, self::NUMERADOS, true) ? $indice : null;
    }

    /** "Expected is one of ( {ns}A, {ns}B )." => "; se esperaba A o B" */
    private function esperados(string $detalle, string $prefijo): string
    {
        if (preg_match('/Expected is (?:one of )?\( (.*) \)/', $detalle, $m) !== 1) {
            return '';
        }
        $nombres = array_map(fn (string $n) => $this->sinNamespaces(trim($n)), explode(',', $m[1]));
        $ultimo = array_pop($nombres);

        return $prefijo . ($nombres === [] ? $ultimo : implode(', ', $nombres) . " o $ultimo");
    }

    private function sinNamespaces(string $texto): string
    {
        return (string) preg_replace('/\{[^}]*\}/', '', $texto);
    }
}
