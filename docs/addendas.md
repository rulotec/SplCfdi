# Addendas

La Addenda (`cfdi:Addenda`) lleva datos que pide el **cliente** (orden de compra, código de proveedor,
partes…), no el SAT. No forma parte de la cadena original: no afecta el sello ni el timbre, y puede
agregarse antes o después de timbrar.

## Incluidas

| Clave | Cliente | Clase | XSD |
|---|---|---|---|
| `IEX` | American Axle (AAM), versión 1.1 | `Addendas\AmericanAxleIex` | `resources/addendas/IEX.xsd` |
| `MPNP` | Metalsa, proveedor no productivo, versión 1.0 | `Addendas\MetalsaMpnp` | `resources/addendas/MPNP.xsd` |
| `PPY` | Stellantis (Chrysler) México, versión 1.0 | `Addendas\StellantisPpy` | `resources/addendas/PPY.xsd` |
| `PSV` | Volkswagen de México, servicios, versión 1.0 | `Addendas\VolkswagenPsv` | `resources/addendas/PSV.xsd` |
| `SANMINA` | Sanmina-SCI (sin detalle por concepto) | `Addendas\Sanmina` | `resources/addendas/SANMINA.xsd` |

Ejemplos en `tests/Fixtures/addendas`: CFDI reales del generador anterior con los datos sustituidos
por ficticios (RFC de prueba del SAT, importes, contactos, códigos); la estructura es la original. Marelli se
implementará cuando tengamos su XSD.

## Modelo

Cada addenda **declara** qué datos necesita (`Addenda::campos()`, una lista de `CampoAddenda`) y
**calcula** lo demás desde el CFDI: montos, cantidades, folio, fecha, moneda, descripción y tipo de
documento salen de `ComprobanteCalculado`. Así el host no sabe nada de cada cliente: muestra y guarda
los campos que la addenda declare.

`CampoAddenda` trae `nombre`, `etiqueta`, `tipo` (Texto, Entero, Decimal, Opcion), `requerido`,
longitudes, `patron`, `opciones`, `ayuda`, `porDefecto` (qué se usa si se omite) y `nivel`:

| Nivel | Qué es | Ejemplos | Dónde lo guardaría el host |
|---|---|---|---|
| `Cliente` | Fijo para ese receptor | código de proveedor, CLABE, división de VW | En la cuenta del cliente |
| `Comprobante` | Cambia en cada factura | solicitante, remisión, planta destino | En la factura |
| `Concepto` | Cambia en cada concepto | orden de compra, posición, número de material | En la partida |

El host entrega los valores en `DatosAddenda`, dentro de `Comprobante::$addenda`:

```php
new DatosAddenda('PSV',
    valores: ['division' => 'VW', 'codigoProveedor' => '1234567890', /* … */ 'ordenCompra' => '9000000001'],
    conceptos: [0 => ['posicion' => '1'], 1 => ['posicion' => '2', 'ordenCompra' => '4590214337']],
);
```

- `valores` lleva los campos de nivel Cliente y Comprobante. Un campo de nivel Concepto puesto aquí se
  aplica a todos los conceptos que no lo traigan (p. ej. una sola orden de compra para la factura).
- `conceptos` usa el mismo índice (desde 0) que `Comprobante::$conceptos`.
- Un valor vacío o solo con espacios cuenta como no capturado.

## Validación

| Qué | Dónde | `campo` de la violación |
|---|---|---|
| Clave inexistente, campos que la addenda no define, conceptos inexistentes | `RuleAddenda` | `Addenda`, `Addenda.otro`, `Addenda.Concepto[4]` |
| Requerido, tipo, longitud, patrón, opciones de cada campo | `RuleAddenda` | `Addenda.codigoProveedor`, `Addenda.Concepto[2].posicion` |
| Reglas propias (`Addenda::reglas()`), p. ej. PPY y MPNP exigen Folio | La addenda | La que corresponda (`Folio`, `Addenda.tipoDocumentoFiscal`) |
| El nodo generado contra el XSD del cliente | `AddendaSchemaValidator` | Ruta dentro de la addenda: `Addenda.Partes.Parte.posicion` |

Los `campo` de `RuleAddenda` usan los nombres de `CampoAddenda`, para que el host los relacione con
sus propios campos. Códigos: `addenda-desconocida`, `addenda-campo-desconocido`,
`addenda-concepto-inexistente`, `addenda-requerido`, `addenda-tipo`, `addenda-longitud`,
`addenda-patron`, `addenda-tipo-documento`, `addenda-requiere-folio`, `addenda-tasa-iva`; los del XSD
son los mismos `xsd-*` del CFDI.

`cfdv40.xsd` declara la Addenda como `xs:any` estricto: libxml exige conocer el esquema de su contenido
y rechazaría cualquier addenda. Por eso `LibXmlSchemaValidator` la quita antes de validar el CFDI y
`AddendaSchemaValidator` valida el nodo de cada addenda aparte, contra su XSD (la reconoce por su
namespace; las que no están registradas no se revisan).

## Uso

### Al generar

`CfdiFactory::create()` ya incluye las addendas: si `Comprobante::$addenda` existe, `generate()` y
`draft()` la ponen en el XML y `validate()` la revisa. Como el resto del CFDI, `generate()` solo
devuelve XML sin violaciones y `draft()` lo devuelve aunque las haya.

### Después de timbrar

Para PACs que no aceptan addendas, o datos que llegan después:

```php
$resultado = CfdiFactory::createAddendaService()->add($xmlTimbrado, $comprobante);
$xmlConAddenda = $resultado->xml;   // null si hay violaciones
```

Solo revisa la addenda (no las reglas del SAT, que ya se cumplieron al timbrar: p. ej. la ventana de 72
horas fallaría). Fuera de `cfdi:Addenda` el XML no cambia; si ya tenía una, se reemplaza.

### En un host (SplVtCfdi)

- La lista de addendas para elegir por cliente: `AddendaRegistry::todas()` (clave => `nombre()`).
- El formulario o el mapeo de campos: `campos()`, agrupados por `nivel`.
- Las violaciones `Addenda.<nombre>` / `Addenda.Concepto[n].<nombre>` se señalan en el campo del host
  mapeado a ese nombre.
- Addendas propias del host: `CfdiFactory::create(addendas: AddendaRegistry::standard($math)->con(new MiAddenda()))`.

## Agregar una addenda

1. Copia el XSD del cliente a `resources/addendas/<CLAVE>.xsd`.
2. Crea `src/Addendas/<Cliente><Clave>.php` que extienda `AddendaBase` (o implemente `Addenda`):
   - `clave()`, `nombre()`, `namespace()` (el `targetNamespace` del XSD).
   - `campos()`: solo lo que el CFDI no trae, con las restricciones del XSD (longitudes, patrones,
     opciones) para que el usuario vea el error en su campo antes que el del XSD.
   - `crear()`: arma el nodo con `nodo()` en el orden del XSD. Los decimales con `decimales()` (los que
     admite el XSD), textos con `recortar()`, unidad con `unidad()`, notas largas con `notas()`. No debe
     lanzar con datos incompletos (el borrador se genera igual).
   - `reglas()`: lo que no se expresa por campo (p. ej. `reglaTipoDocumento()`).
3. Regístrala en `AddendaRegistry::standard()`.
4. Pon un CFDI real del cliente en `tests/Fixtures/addendas` y su caso en `EjemplosAddenda::todos()`:
   `EjemplosAddendaTest` comprueba que con esos datos se genera la misma addenda y que cumple su XSD.
   `AddendaRegistryTest` revisa que el XSD exista y coincida el namespace.
5. Agrégala a la tabla de este documento.

Ningún otro archivo cambia.

### Rarezas de un cliente

Si un portal exige algo distinto de lo que dice su XSD, se vuelve una opción del constructor de esa
addenda, con el comportamiento correcto por defecto. Ejemplo: `new VolkswagenPsv($math, longitudNota: 25)`
reparte las notas en nodos de 25 caracteres, como hacía el generador anterior (el XSD admite 254).

Diferencias conocidas con los ejemplos del generador anterior (FacturacionCFDI), todas a favor del XSD:

- PSV: `cantidadMaterial` con 4 decimales y `precioUnitario` con 5 (el ejemplo traía 6 y 4).
- PPY: `montoTotal` y `otrosCargos/@monto` con 2 decimales (el ejemplo traía 6); no se declara
  `xmlns:xsi` con el namespace de PPY.
- IEX: el texto se escribe en UTF-8 correcto (el ejemplo trae doble codificación: "RevisiÃ³n").
- PSV: `tipoMoneda` es la Moneda del CFDI; para mandar `MXP` como el ejemplo se captura el campo
  `tipoMoneda`.
- MPNP: el generador anterior escribía `TipoDocumentoFiscal` (el XSD pide `tipoDocumentoFiscal`) y dejaba
  vacíos atributos obligatorios (`codigo` de destino, `fechaRecibo`, `numeroRecibo`, `ordencompra`…); el
  ejemplo ya trae esos datos. `proveedor/@codigo` es el RFC del emisor salvo que se capture `codigoProveedor`.
- SANMINA: el ejemplo declara `xmlns:xsi` con el namespace de XML Schema y un `xsi:schemaLocation` que el
  XSD no admite; la addenda generada no lo lleva.

Si algún portal rechaza alguna de estas, se agrega la opción en esa addenda.

## Pendiente

- Nodos opcionales que no aparecen en los ejemplos: `Archivo` (PSV, IEX: archivos en base64, que
  FacturacionCFDI agregaba después de timbrar), `Origen`/`Destino`/`Medidas` (PSV),
  `origen`/`receiving`/`proyecto`/`cargosCreditos` (PPY), `Nota` del comprobante, `Cancelaciones`
  múltiples, `fechaRecibo` (PPY).
- Marelli (`xmlns:fa="finanzas"`): sin XSD.
- MPNP: los nodos opcionales `archivo` y `cargosCreditos`.
- Montos de línea con descuento: hoy se usa el `Importe` del concepto (cantidad × valor unitario).
