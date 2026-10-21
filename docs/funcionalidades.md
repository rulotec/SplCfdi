# Funcionalidades

Estado de `spl/cfdi-core` respecto a CFDI 4.0. El **sellado** (cadena original + sello con CSD) y el
**timbrado** (PAC) están fuera de alcance por ahora. Prioridad del proyecto: que los errores en los
datos se detecten pronto y se expliquen con mensajes claros para el usuario final.

Fuentes: Anexo 20 (Guía de llenado del CFDI 4.0), Matriz de errores CFDI 4.0 y `catCFDI.xls`,
publicados en <http://omawww.sat.gob.mx/tramitesyservicios/Paginas/anexo_20.htm>. Los códigos
`CFDI40xxx` de este documento son los de la matriz de errores.

## Implementado

### Modelo y generación de XML
- Comprobante 4.0: `Version`, `Serie`, `Folio`, `Fecha` (hora local, sin zona), `FormaPago`,
  `MetodoPago`, `Moneda`, `TipoCambio`, `TipoDeComprobante`, `Exportacion`, `LugarExpedicion`,
  `Confirmacion`.
- `InformacionGlobal` (CFDI global a público en general): `Periodicidad`, `Meses`, `Año`.
- `CfdiRelacionados` (`TipoRelacion` + UUIDs), uno por tipo de relación: notas de crédito (Egreso,
  relación 01), sustituciones (04), etc.
- `Emisor` (`Rfc`, `Nombre`, `RegimenFiscal`) y `Receptor` (`Rfc`, `Nombre`,
  `DomicilioFiscalReceptor`, `RegimenFiscalReceptor`, `UsoCFDI`).
- `Conceptos`: `ClaveProdServ`, `Cantidad`, `ClaveUnidad`, `Unidad`, `Descripcion`,
  `ValorUnitario`, `Importe`, `Descuento`, `ObjetoImp`.
- Impuestos por concepto: traslados con `TipoFactor` Tasa, Cuota (IEPS por cuota; el host indica la
  `Base` en unidades) o Exento; retenciones con Tasa o Cuota.
- Nodo `Impuestos` del comprobante: traslados agrupados por impuesto/factor/tasa, retenciones
  agrupadas por impuesto, totales.
- XML con namespaces y `schemaLocation`, orden de nodos del esquema, atributos opcionales omitidos.

### Cálculo
- Importe, descuento, base, impuestos y totales con aritmética decimal exacta (bcmath, sin floats).
- Redondeo half-up; escala configurable (`DecimalConfiguration`).
- Decimales por moneda tomados de `c_Moneda` (JPY 0, MXN 2…): el host ya no los indica.
- La base del IVA incluye el IEPS trasladado del concepto (LIVA art. 12).
- Redondeo único sobre la suma agrupada a nivel comprobante.

### Validación
Los datos inválidos **nunca lanzan excepciones**: `CfdiService::generate()` (y `validate()`, que no
devuelve XML) entregan un `CfdiResult` con todas las violaciones. Cada `Violation` trae `code`
estable, `message` en español para el usuario final y `campo` (ruta en el XML, p. ej.
`Concepto[2].Traslado[1].TasaOCuota`). El host no necesita validar antes; solo muestra la lista.

1. **Formato** (`FormatRuleSet`, antes de calcular). Si algo falla, el cálculo no es posible y solo
   se devuelven estas violaciones (`calculado` y `xml` son null).

   | Código | Revisión |
   |---|---|
   | `formato-decimal` | Cantidad, ValorUnitario, Descuento, TasaOCuota y Base (Cuota): número no negativo, máx. 6 decimales |
   | `formato-clave-prod-serv`, `formato-codigo-postal` | Ocho / cinco dígitos (con aviso sobre ceros perdidos) |
   | `moneda-desconocida`, `decimales-moneda` | Moneda en c_Moneda; sus decimales caben en la configuración |
   | `formato-tipo-cambio`, `formato-confirmacion` | TipoCambio > 0 con máx. 6 decimales; Confirmacion de 5 caracteres alfanuméricos |
   | `formato-meses`, `formato-anio` | InformacionGlobal: dos dígitos / cuatro dígitos |
   | `cfdi-relacionados-vacio`, `formato-uuid` | Cada CfdiRelacionados con al menos un UUID válido |
   | `tasa-requerida`, `exento-con-tasa`, `retencion-exenta` | Coherencia TipoFactor ↔ TasaOCuota |
   | `base-cuota-requerida`, `base-solo-con-cuota` | La Base del host va solo con Cuota, y es obligatoria ahí |
   | `rfc-formato` | Forma del RFC de emisor y receptor (incluye fecha válida) |

2. **Reglas del SAT** (`RuleSet`, sobre el cálculo). Se devuelven todas, junto con `calculado`.

   | Código | Regla |
   |---|---|
   | `fecha-futura`, `fecha-vencida` | Fecha no futura y a no más de 72 h |
   | `informacion-global-requerida`, `informacion-global-receptor` | InformacionGlobal para público en general; Rfc y Nombre del receptor en un CFDI global |
   | `informacion-global-anio` | Año de InformacionGlobal = año de la Fecha o el anterior |
   | `bimestral-solo-rif` | Periodicidad bimestral solo con régimen 621 |
   | `metodo-pago-global` | CFDI global con MetodoPago PUE |
   | `forma-pago-no-aplica`, `metodo-pago-no-aplica` | Sin FormaPago en T, N y P (CFDI40103); sin MetodoPago en T y P (CFDI40125) |
   | `ppd-requiere-forma-pago-99`, `pue-con-forma-pago-99` | PPD ⇒ FormaPago 99 (CFDI40105); PUE ⇒ FormaPago ≠ 99 |
   | `tipo-cambio-mxn`, `tipo-cambio-xxx`, `tipo-cambio-requerido` | TipoCambio omitido o 1 en MXN, ausente en XXX, obligatorio en otra moneda (CFDI40114–40116) |
   | `tipo-cambio-fuera-de-rango` | Con un `TipoCambioFix`: fuera de FIX × (1 ± variación de c_Moneda) exige Confirmacion (CFDI40118) |
   | `rfc-generico-regimen`, `rfc-generico-uso-cfdi` | XAXX010101000 / XEXX010101000 siempre con régimen 616 (CFDI40159) y UsoCFDI S01 (CP01 en pagos) |
   | `regimen-emisor-persona`, `regimen-receptor-persona` | Régimen válido para el tipo de persona según c_RegimenFiscal (CFDI40141, CFDI40158) |
   | `domicilio-receptor-generico` | RFC genérico: DomicilioFiscalReceptor = LugarExpedicion |
   | `impuestos-en-traslado-o-pago`, `descuento-en-traslado-o-pago` | Sin Impuestos ni Descuento en tipos T y P |
   | `meses-periodicidad` | Meses 01–12, o 13–18 si la periodicidad es bimestral |
   | `uso-cfdi-persona`, `uso-cfdi-regimen` | UsoCFDI válido para tipo de persona y régimen del receptor |
   | `objeto-imp-02-sin-impuestos` | ObjetoImp 02 exige el nodo Impuestos (CFDI40171) |
   | `objeto-imp-sin-impuestos` | ObjetoImp 01, 03, 04 y 05 sin nodo Impuestos (CFDI40172) |
   | `objeto-imp-sin-iva-ieps` | ObjetoImp 06 y 08: sin IVA ni IEPS; se admite retención de ISR (CFDI140226) |
   | `objeto-imp-sin-iva`, `objeto-imp-07-sin-ieps` | ObjetoImp 07: sin IVA y con traslado de IEPS (CFDI140227) |
   | `objeto-imp-exento` | Un traslado Exento exige ObjetoImp 02 |
   | `tasa-o-cuota-invalida` | TasaOCuota en c_TasaOCuota: valor fijo o rango según impuesto, factor, traslado/retención y vigencia (CFDI40179, CFDI40185) |
   | `descuento-mayor-que-importe` | Descuento de concepto ≤ Importe |
   | `base-cero` | Base de cada impuesto > 0 |
   | `total-negativo` | Total no negativo |
   | `catalogo-invalido` | Claves existentes en los 15 catálogos embebidos, incluidos ClaveProdServ, ClaveUnidad y códigos postales |

3. **XSD** (`LibXmlSchemaValidator`, sobre el XML generado). Se agregan a las anteriores, salvo las de
   un `campo` que ya tiene violación de una regla (la regla lo explica mejor).

   | Código | Error del esquema |
   |---|---|
   | `xsd-catalogo` | Valor fuera de la enumeración: `UsoCFDI "ZZZ" no existe en el catálogo c_UsoCFDI.` |
   | `xsd-patron` | No cumple el patrón; con explicación para Rfc, códigos postales, UUID, etc. |
   | `xsd-minimo`, `xsd-maximo`, `xsd-longitud`, `xsd-decimales`, `xsd-tipo` | Facetas numéricas, de longitud y de tipo |
   | `xsd-requerido`, `xsd-no-permitido` | Atributo obligatorio faltante o no permitido |
   | `xsd-estructura` | Nodo fuera de lugar o faltante, raíz que no es `cfdi:Comprobante` |
   | `xsd` | Cualquier otro mensaje de libxml (sin namespaces) |
   | `xml-vacio`, `xml-mal-formado` | El XML no se pudo leer |

   - Se valida contra una **copia de validación** (`resources/xsd/validacion`) en la que c_ClaveProdServ,
     c_ClaveUnidad y c_CodigoPostal son patrones: con las enumeraciones completas libxml tarda ~0.5 s
     por CFDI y hasta 40 s en reportar un código postal inexistente. Esas claves las revisa
     `RuleCatalogos`.
   - Mientras no haya sellado, el validador pone valores ficticios en `Sello`, `NoCertificado` y
     `Certificado`. El `TimbreFiscalDigital` no hace falta: va en `Complemento`, que el XSD trata
     como opcional, y lo agrega el PAC.
   - `CfdiFactory::create(validarXsd: false)` omite este paso.

### Catálogos
- 15 catálogos embebidos generados desde `catCFDI.xsd` (los tres grandes se cargan solo al
  consultarlos) y datos de `catCFDI.xls` (decimales y variación de c_Moneda, tipo de persona de
  c_RegimenFiscal, tabla c_TasaOCuota), exclusiones manuales, claves con nombre, matriz UsoCFDI ×
  régimen, y la herramienta `bin/cfdi-catalogs check|write|xls` que detecta desfases. Ver
  [`catalogos.md`](catalogos.md).

## Pendiente

### Errores claros
1. **Formatos aún sin revisar antes del XSD**: longitudes máximas de texto (Descripcion, Nombre…),
   caracteres no permitidos. Hoy los detecta el XSD (`xsd-longitud`, `xsd-patron`) después del cálculo.
2. Publicar la lista de códigos de `Violation` como API estable para que el host los traduzca o
   personalice.

### Atributos y nodos del CFDI 4.0
- Comprobante: `CondicionesDePago`.
- Receptor extranjero: `ResidenciaFiscal`, `NumRegIdTrib` (catálogo `c_Pais`).
- Emisor: `FacAtrAdquirente`.
- Concepto: `NoIdentificacion`, `ACuentaTerceros`, `InformacionAduanera`, `CuentaPredial`, `Parte`.

### Reglas del SAT
- `Exportacion` distinta de 01 y sus requisitos (Comercio Exterior).
- `Confirmacion` cuando el **Total** excede los límites del SAT (CFDI40127: la Confirmacion no debe
  existir si el TipoCambio y el Total están dentro de rango). Hoy solo se revisa el TipoCambio, y
  solo si el host implementa `TipoCambioFix`. Nota: la Guía de llenado indica que la validación del
  TipoCambio contra el FIX aplicará cuando el SAT publique el procedimiento.
- Año de `InformacionGlobal`: la matriz (CFDI40136) admite el ejercicio en curso o hasta cinco
  anteriores; `RuleInformacionGlobalAnio` solo acepta el año de la Fecha o el anterior. Revisar.

### Mantenibilidad
- `UsoCfdiMatrix` es estática: inyectarla como los catálogos para poder sustituirla en pruebas o
  actualizarla sin tocar código.
- `Comprobante::$version` es obligatorio aunque solo se soporta "4.0".
- Descripciones de catálogo en los mensajes: hoy solo las de c_RegimenFiscal
  (`"605" (Sueldos y Salarios…)`). Para los demás habría que importarlas de `catCFDI.xls`.

### Fuera de alcance por ahora
- Cadena original (XSLT), sellado con CSD, timbrado con PAC, cancelación.
- Representación impresa (PDF).
- Complementos:
  - Recepción de pagos 2.0 (tipo P; imprescindible para facturar en PPD).
  - Nómina 1.2 (tipo N).
  - Carta Porte 3.1 (tipo T e I con traslado de mercancías).
  - Comercio Exterior 2.0.
- Punto de extensión genérico para complementos y addendas.
