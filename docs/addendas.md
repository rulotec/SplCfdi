# Addendas

La Addenda (`cfdi:Addenda`) lleva datos que pide el **cliente** (orden de compra, código de proveedor,
partes…), no el SAT. No forma parte de la cadena original: no afecta el sello ni el timbre, y puede
agregarse antes o después de timbrar.

## Incluidas

| Clave | Cliente | Clase | XSD |
|---|---|---|---|
| `IEX` | American Axle (AAM), versión 1.1 | `Addendas\AmericanAxleIex` | `resources/addendas/IEX.xsd` |
| `MARELLI` | Marelli Mexicana (namespace `finanzas`) | `Addendas\MarelliFinanzas` | `resources/addendas/MARELLI.xsd` |
| `MPNP` | Metalsa, proveedor no productivo, versión 1.0 | `Addendas\MetalsaMpnp` | `resources/addendas/MPNP.xsd` |
| `PPY` | Stellantis (Chrysler) México, versión 1.0 | `Addendas\StellantisPpy` | `resources/addendas/PPY.xsd` |
| `PSV` | Volkswagen de México, servicios, versión 1.0 | `Addendas\VolkswagenPsv` | `resources/addendas/PSV.xsd` |
| `SANMINA` | Sanmina-SCI (sin detalle por concepto) | `Addendas\Sanmina` | `resources/addendas/SANMINA.xsd` |

Ejemplos en `tests/Fixtures/addendas`: CFDI reales del generador anterior con los datos sustituidos
por ficticios (RFC de prueba del SAT, importes, contactos, códigos); la estructura es la original.

## Modelo

Cada addenda **declara** qué datos necesita (`Addenda::campos()`, una lista de `CampoAddenda`) y
**calcula** lo demás desde el CFDI: montos, cantidades, folio, fecha, moneda, descripción y tipo de
documento salen de `ComprobanteCalculado`. Así el host no sabe nada de cada cliente: muestra y guarda
los campos que la addenda declare.

`CampoAddenda` trae `nombre`, `etiqueta`, `tipo` (Texto, Entero, Decimal, Opcion), `requerido`,
longitudes, `patron`, `opciones`, `ayuda`, `porDefecto` (qué se usa si se omite, en palabras) y `nivel`.
El valor que se usa lo da `Addenda::porDefecto($comprobante)` (la Moneda, la Unidad de cada concepto…):
`RuleAddenda` y el XML completan con él lo que el host deja vacío, y el formulario lo muestra como valor
por omisión.

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

### Formulario de captura

Para no crear un campo en el host por cada dato de cada addenda (sobre todo los de cada concepto), el host
entrega lo que ya sabe y el usuario captura o corrige el resto en un formulario. Todo vive en
`Application\AddendaForm` y no depende de ningún framework:

| Clase | Qué es |
|---|---|
| `AddendaDefaults` | Lo que el host ya tiene: `DefaultValue(valor, origen)` por campo (y por concepto). Un campo de nivel Concepto en `values` vale para todos los conceptos. |
| `AddendaOverrides` | Lo que el usuario capturó en esa factura. `fromArray()` lee lo que manda el formulario (descarta lo que no es texto); `toArray()` es lo que el host guarda. |
| `AddendaValues` | El valor de cada campo: lo capturado o, si no, el del host. `datos()` es el `DatosAddenda` para generar el CFDI. |
| `AddendaFormService` | `build($comprobante, $clave, $defaults, $overrides, $avisos)`: arma el formulario y revisa la addenda resultante. `$avisos` (índice del concepto => texto) son las líneas que el host quiere que el usuario revise, p. ej. porque cambió su producto. |
| `AddendaForm` | El resultado: `datos()` y `toArray()`, el contrato con la página del formulario. |

```php
$form = CfdiFactory::createAddendaFormService()->build(
    $comprobante, 'PSV',
    new AddendaDefaults(['codigoProveedor' => new DefaultValue('1234567890', 'Configuración')]),
    AddendaOverrides::fromArray($guardado),
);
echo json_encode($form->toArray());
```

Reglas de los valores:

- Lo capturado gana sobre el valor del host, y el del host sobre el de la addenda (`porDefecto()`, con
  `CampoAddenda::$porDefecto` como `source`). Una cadena vacía lo borra: la addenda usa su valor por
  omisión si tiene, o va sin ese dato o se reporta como faltante.
- Capturar el mismo valor que el del host no cuenta como modificado: si el valor del host cambia después,
  la factura lo sigue.
- Lo capturado para conceptos que la factura ya no tiene, o para nombres que la addenda no define, no se usa.
- `build()` ignora la addenda que traiga `$comprobante`; usa la que resulta de valores y capturas.

`build()` revisa solo la addenda, no las reglas del SAT: el formato del comprobante (sin él no se puede
calcular), `RuleAddenda` y, cuando esas reglas pasan, el XSD del cliente (antes solo repetiría lo mismo).

#### Contrato (`AddendaForm::toArray()`)

```jsonc
{
  "addenda": { "key": "PSV", "name": "Volkswagen de México – Servicios (PSV)" },  // name null si la clave no existe
  "fields": [                       // en el orden de Addenda::campos(); vacío si la clave no existe
    {
      "name": "codigoProveedor", "label": "Código de proveedor",
      "level": "cliente",           // cliente | comprobante | concepto
      "type": "texto",              // texto | entero | decimal | opcion
      "required": true, "minLength": null, "maxLength": null,
      "pattern": "[0-9]{10}",       // expresión regular sin delimitadores; debe cumplirse completa
      "options": [],                // [{ "value": "VW", "label": "Volkswagen México" }] con type opcion
      "help": "diez dígitos, asignado por VW",
      "fallback": null              // qué usa la addenda si se deja vacío, p. ej. "La Moneda del CFDI"
    }
  ],
  "header": {                       // campos de nivel cliente y comprobante
    "codigoProveedor": { "value": "1234567890", "default": "1234567890", "source": "Configuración", "overridden": false }
  },
  "lines": [
    {
      "index": 0, "number": 1,      // index es el que usan AddendaOverrides y violations
      "productKey": "86101705",     // ClaveProdServ
      "description": "Servicio de prueba", "quantity": "1.000000", "unit": "MON", "unitPrice": "5000.000000",
      "amount": "5000.000000",      // Importe del CFDI; null si la factura tiene errores de formato y no se pudo calcular
      "notice": null,               // aviso del host para esta línea
      "values": {                   // campos de nivel concepto
        "posicion": { "value": "1", "default": null, "source": null, "overridden": true }
      }
    }
  ],
  "violations": [
    {
      "code": "addenda-requerido", "message": "Addenda …: falta Posición en la orden de compra.",
      "path": "Addenda.Concepto[1].posicion",
      "field": "posicion", "line": 0  // ambos null si no corresponde a un campo del formulario
    }
  ],
  "valid": false
}
```

- Cada celda: `value` es lo que va en la addenda (null si queda vacía), `default` y `source` lo que da el
  host (para mostrarlo y restablecerlo), `overridden` si el usuario capturó algo distinto.
- `field` y `line` ubican la violación en un campo; con ambos null (un problema de la factura, o una ruta
  del XSD dentro del XML de la addenda como `Addenda.Proveedor.codigo`) la página la muestra aparte.
- Lo que el formulario envía es `AddendaOverrides::toArray()`:
  `{ "values": { "remision": "REM02" }, "lines": { "0": { "posicion": "1" } } }`.

## Agregar una addenda

1. Copia el XSD del cliente a `resources/addendas/<CLAVE>.xsd`.
2. Crea `src/Addendas/<Cliente><Clave>.php` que extienda `AddendaBase` (o implemente `Addenda`):
   - `clave()`, `nombre()`, `namespace()` (el `targetNamespace` del XSD).
   - `campos()`: solo lo que el CFDI no trae, con las restricciones del XSD (longitudes, patrones,
     opciones) para que el usuario vea el error en su campo antes que el del XSD.
   - `porDefecto()`: lo que sale del comprobante cuando el dato se deja vacío (con su `porDefecto` en
     `campos()`). `tipoDocumentoPorDefecto()` y `unidadesPorDefecto()` cubren los casos comunes.
   - `crear()`: arma el nodo con `nodo()` en el orden del XSD. Recibe los datos ya completados con
     `porDefecto()`, así que no repite esos valores. Los decimales con `decimales()` (los que
     admite el XSD), textos con `recortar()`, unidad con `unidad()`, notas largas con `notas()`. No debe
     lanzar con datos incompletos (el borrador se genera igual).
   - `reglas()`: lo que no se expresa por campo (p. ej. `reglaTipoDocumento()`); también recibe los datos
     completados.
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
- MPNP: los nodos opcionales `archivo` y `cargosCreditos`.
- Montos de línea con descuento: hoy se usa el `Importe` del concepto (cantidad × valor unitario).
