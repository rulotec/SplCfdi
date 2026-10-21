# Funcionalidades

Estado de `spl/cfdi-core` respecto a CFDI 4.0. El **sellado** (cadena original + sello con CSD) y el
**timbrado** (PAC) están fuera de alcance por ahora. Prioridad del proyecto: que los errores en los
datos se detecten pronto y se expliquen con mensajes claros para el usuario final.

## Implementado

### Modelo y generación de XML
- Comprobante 4.0: `Version`, `Serie`, `Folio`, `Fecha` (hora local, sin zona), `FormaPago`,
  `MetodoPago`, `Moneda`, `TipoDeComprobante`, `Exportacion`, `LugarExpedicion`.
- `InformacionGlobal` (CFDI global a público en general): `Periodicidad`, `Meses`, `Año`.
- `Emisor` (`Rfc`, `Nombre`, `RegimenFiscal`) y `Receptor` (`Rfc`, `Nombre`,
  `DomicilioFiscalReceptor`, `RegimenFiscalReceptor`, `UsoCFDI`).
- `Conceptos`: `ClaveProdServ`, `Cantidad`, `ClaveUnidad`, `Unidad`, `Descripcion`,
  `ValorUnitario`, `Importe`, `Descuento`, `ObjetoImp`.
- Impuestos por concepto: traslados con `TipoFactor` Tasa o Exento; retenciones con Tasa.
- Nodo `Impuestos` del comprobante: traslados agrupados por impuesto/factor/tasa, retenciones
  agrupadas por impuesto, totales.
- XML con namespaces y `schemaLocation`, orden de nodos del esquema, atributos opcionales omitidos.

### Cálculo
- Importe, descuento, base, impuestos y totales con aritmética decimal exacta (bcmath, sin floats).
- Redondeo half-up; escala configurable (`DecimalConfiguration`), decimales por moneda.
- Redondeo único sobre la suma agrupada a nivel comprobante.

### Validación
Los datos inválidos **nunca lanzan excepciones**: `CfdiService::generate()` (y `validate()`, que no
genera XML) devuelven un `CfdiResult` con todas las violaciones. Cada `Violation` trae `code`
estable, `message` en español para el usuario final y `campo` (ruta en el XML, p. ej.
`Concepto[2].Cantidad`). El host no necesita validar antes; solo muestra la lista.

1. **Formato** (`FormatRuleSet`, antes de calcular). Si algo falla, el cálculo no es posible y solo
   se devuelven estas violaciones (`calculado` y `xml` son null).

   | Código | Revisión |
   |---|---|
   | `formato-decimal` | Cantidad, ValorUnitario, Descuento y TasaOCuota: número no negativo, máx. 6 decimales |
   | `formato-clave-prod-serv`, `formato-codigo-postal` | Ocho / cinco dígitos (con aviso sobre ceros perdidos) |
   | `formato-moneda`, `decimales-moneda` | Código de tres letras; decimales dentro de la escala |
   | `formato-meses`, `formato-anio` | InformacionGlobal: dos dígitos / cuatro dígitos |
   | `tasa-requerida`, `exento-con-tasa`, `retencion-exenta` | Coherencia TipoFactor ↔ TasaOCuota |
   | `rfc-formato` | Forma del RFC de emisor y receptor (incluye fecha válida) |

2. **Reglas del SAT** (`RuleSet`, sobre el cálculo). Se devuelven todas, junto con `calculado`.

  | Código | Regla |
  |---|---|
  | `fecha-futura`, `fecha-vencida` | Fecha no futura y a no más de 72 h |
  | `informacion-global-requerida`, `informacion-global-receptor` | InformacionGlobal para público en general y datos del receptor en un CFDI global |
  | `informacion-global-anio` | Año de InformacionGlobal = año de la Fecha o el anterior |
  | `bimestral-solo-rif` | Periodicidad bimestral solo con régimen 621 |
  | `metodo-pago-global` | CFDI global con MetodoPago PUE |
  | `domicilio-receptor-generico` | RFC genérico: DomicilioFiscalReceptor = LugarExpedicion |
  | `impuestos-en-traslado-o-pago`, `descuento-en-traslado-o-pago` | Sin Impuestos ni Descuento en tipos T y P |
  | `meses-periodicidad` | Meses 01–12, o 13–18 si la periodicidad es bimestral |
  | `uso-cfdi-persona`, `uso-cfdi-regimen` | UsoCFDI válido para tipo de persona y régimen del receptor |
  | `objeto-imp-01-con-impuestos`, `objeto-imp-02-sin-impuestos`, `objeto-imp-exento` | ObjetoImp coherente con el desglose (claves 01 y 02) |
  | `descuento-mayor-que-importe` | Descuento de concepto ≤ Importe |
  | `base-cero` | Base de impuestos > 0 |
  | `total-negativo` | Total no negativo |
  | `catalogo-invalido` | Claves existentes en los 10 catálogos embebidos |

- **Validación XSD** (`LibXmlSchemaValidator`) contra `cfdv40.xsd` + `catCFDI.xsd`.

### Catálogos
- 10 catálogos embebidos generados desde `catCFDI.xsd`, exclusiones manuales, claves con nombre,
  matriz UsoCFDI × régimen, y herramienta `bin/cfdi-catalogs check|write [--fetch]` que detecta
  desfases. Ver [`catalogos.md`](catalogos.md).

## Pendiente

### Errores claros (prioridad alta)
1. **Errores XSD en inglés y crudos.** libxml devuelve, p. ej.,
   `Element '{http://www.sat.gob.mx/cfd/4}Concepto', attribute 'ClaveUnidad': [facet 'enumeration'] The value 'XYZ' is not an element of the set {...}`
   (el conjunto puede tener miles de valores). Falta traducirlos a
   `Concepto: ClaveUnidad "XYZ" no existe en el catálogo c_ClaveUnidad.` con su `campo`.
2. **Validación XSD fuera del flujo.** `CfdiService` no valida contra el esquema; el host debe
   hacerlo, `LibXmlSchemaValidator` aún lanza excepción en vez de devolver `Violation`s, y sin sello
   falla por `Sello`/`Certificado`/`NoCertificado`. Falta integrarla en `CfdiResult`
   (ignorando esos atributos mientras no haya sellado).
3. **Formatos aún sin revisar antes del XSD**: ClaveUnidad, longitudes máximas de texto
   (Descripcion, Nombre…), caracteres no permitidos. Hoy solo los detecta el XSD.
4. **Descripciones de catálogo en los mensajes** (`601 – General de Ley Personas Morales`): requiere
   leer `catCFDI.xls`, el XSD no las trae.
5. Publicar la lista de códigos de `Violation` como API estable para que el host los traduzca o
   personalice.

### Atributos y nodos del CFDI 4.0
- Comprobante: `TipoCambio` (obligatorio si Moneda ≠ MXN/XXX), `CondicionesDePago`, `Confirmacion`;
  moneda `XXX`.
- Receptor extranjero: `ResidenciaFiscal`, `NumRegIdTrib` (catálogo `c_Pais`).
- Emisor: `FacAtrAdquirente`.
- Concepto: `NoIdentificacion`, `ACuentaTerceros`, `InformacionAduanera`, `CuentaPredial`, `Parte`.
- `CfdiRelacionados` (`c_TipoRelacion`): necesario para notas de crédito (Egreso) y sustituciones.
- `TipoFactor` Cuota (IEPS por cuota).

### Reglas del SAT
- `ObjetoImp` 03–08.
- `TasaOCuota` contra `c_TasaOCuota` (valores fijos y rangos por impuesto/factor, traslado/retención).
- `MetodoPago` PPD ⇒ `FormaPago` 99; PUE ⇒ `FormaPago` ≠ 99.
- Sin `MetodoPago`/`FormaPago` en tipos T, P y N.
- `RegimenFiscal` del emisor válido para su tipo de persona (columnas Física/Moral de c_RegimenFiscal).
- RFC `XAXX010101000` siempre con régimen 616 y UsoCFDI S01 (hoy solo se exige en el CFDI global).
- `Exportacion` distinta de 01 y sus requisitos (Comercio Exterior).
- `Confirmacion` cuando el Total o el TipoCambio exceden los límites del SAT.
- Validación de `TipoCambio` contra el rango de variación de `c_Moneda`.
- Embeber los decimales por moneda de `c_Moneda` en lugar de pedirlos al host.

### Complementos
- Recepción de pagos 2.0 (tipo P; imprescindible para facturar en PPD).
- Nómina 1.2 (tipo N).
- Carta Porte 3.1 (tipo T e I con traslado de mercancías).
- Comercio Exterior 2.0.
- Punto de extensión genérico para complementos y addendas.

### Mantenibilidad
- `UsoCfdiMatrix` es estática: inyectarla como los catálogos para poder sustituirla en pruebas o
  actualizarla sin tocar código.
- `Comprobante::$version` es obligatorio aunque solo se soporta "4.0".
- `spl-cfdi-catalogs-fix.zip` en la raíz es un parche ya aplicado (su contenido coincide con el
  código actual): se puede eliminar.
- Sin README ni análisis estático (PHPStan/Psalm).

### Fuera de alcance por ahora
- Cadena original (XSLT), sellado con CSD, timbrado con PAC, cancelación.
- Representación impresa (PDF).
