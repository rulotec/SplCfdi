# Catálogos del SAT

Los catálogos del SAT (`catCFDI.xsd` / `catCFDI.xls`) son la principal fuente de cambios de este
proyecto: el SAT agrega, retira o reinterpreta claves varias veces al año. Este documento dice
**dónde vive cada pieza**, **qué se genera solo y qué se mantiene a mano**, y **cómo actualizar**.

Fuentes oficiales:
- `catCFDI.xsd`: <http://www.sat.gob.mx/sitio_internet/cfd/catalogos/catCFDI.xsd>
- `catCFDI.xls`, matriz de errores y Guía de llenado: página del Anexo 20,
  <http://omawww.sat.gob.mx/tramitesyservicios/Paginas/anexo_20.htm>. El servidor rechaza HTTPS: usa
  `http://`.

## Resumen: dónde vive cada cosa

| Pieza | Ubicación | Origen | ¿Se edita a mano? |
|---|---|---|---|
| Lista de catálogos embebidos | `src/Domain/Catalogs/CatalogName.php` | — | Sí: una constante por catálogo |
| Claves de cada catálogo | `resources/catalogs/c_*.php` | `catCFDI.xsd` | **No**: `bin/cfdi-catalogs write` |
| Columnas que el XSD no trae | `resources/catalogs/xls/c_*.php` | `catCFDI.xls` | **No**: `bin/cfdi-catalogs xls` |
| Copia de los XSD para validar | `resources/xsd/validacion/*.xsd` | `resources/xsd` | **No**: `bin/cfdi-catalogs write` |
| Claves que el XSD aún lista pero el SAT ya no acepta | `resources/catalogs/_excluded.php` | `catCFDI.xls` (fechas de vigencia) | Sí |
| Claves con nombre que usan las reglas | `src/Domain/Catalogs/Claves/*.php` | — | Sí |
| Matriz UsoCFDI × persona × régimen | `src/Domain/Catalogs/UsoCfdiMatrix.php` | `catCFDI.xls` | Sí |
| XSD de referencia | `resources/xsd/{catCFDI,cfdv40,tdCFDI}.xsd` | Portal del SAT | Se reemplazan al actualizar |

## Catálogos embebidos (claves, de `catCFDI.xsd`)

Se validan por existencia en `RuleCatalogos` (violación `catalogo-invalido`).

| Catálogo | Campos del CFDI que valida | Claves con nombre |
|---|---|---|
| `c_TipoDeComprobante` | `TipoDeComprobante` | `Claves\TipoDeComprobante` |
| `c_Exportacion` | `Exportacion` | — |
| `c_MetodoPago` | `MetodoPago` | `Claves\MetodoPago` |
| `c_FormaPago` | `FormaPago` | `Claves\FormaPago` |
| `c_Moneda` | `Moneda` (en `RuleFormatoComprobante`: sin moneda no se calcula) | `Claves\Moneda` |
| `c_RegimenFiscal` | `Emisor.RegimenFiscal`, `Receptor.RegimenFiscalReceptor` | `Claves\RegimenFiscal` |
| `c_UsoCFDI` | `Receptor.UsoCFDI` | `Claves\UsoCfdi` |
| `c_Periodicidad` | `InformacionGlobal.Periodicidad` | `Claves\Periodicidad` |
| `c_Meses` | `InformacionGlobal.Meses` | `Claves\Meses` |
| `c_TipoRelacion` | `CfdiRelacionados.TipoRelacion` | — |
| `c_ObjetoImp` | `Concepto.ObjetoImp` | `Claves\ObjetoImp` |
| `c_Impuesto` | `Traslado.Impuesto`, `Retencion.Impuesto` | `Claves\Impuesto` |
| `c_ClaveProdServ` (~52 700 claves) | `Concepto.ClaveProdServ` | — |
| `c_ClaveUnidad` (~2 400) | `Concepto.ClaveUnidad` | — |
| `c_CodigoPostal` (~95 800) | `LugarExpedicion`, `Receptor.DomicilioFiscalReceptor` | — |

Cada `resources/catalogs/c_<Nombre>.php` devuelve un arreglo de claves **como cadenas**
(`'01'`, nunca `1`: el cero a la izquierda es parte de la clave). `EmbeddedCatalogs` los carga con
`LazyCatalog`: el archivo se lee la primera vez que se consulta, así que los tres grandes (~2 MB)
solo cuestan cuando se usan.

## Datos de `catCFDI.xls`

El XSD solo trae las claves. Lo demás se importa del XLS a `resources/catalogs/xls/`:

| Archivo | Columnas | Lo usa |
|---|---|---|
| `c_Moneda.php` | Decimales, porcentaje de variación | `Monedas` → cálculo (decimales), `RuleTipoCambio` (rango) |
| `c_RegimenFiscal.php` | Aplica a persona Física / Moral, descripción | `RegimenesFiscales` → `RuleRegimenFiscalPersona` |
| `c_TasaOCuota.php` | Fijo o rango, mínimo, máximo, impuesto, factor, traslado/retención, vigencia | `TasasOCuotas` → `RuleTasaOCuota` |

- Las filas de c_Moneda y c_RegimenFiscal con fecha de fin de vigencia vencida no se importan.
- c_TasaOCuota conserva las fechas de vigencia: la regla filtra por la `Fecha` del comprobante, así
  que la actualización anual de la cuota del IEPS no depende de cuándo se importó.
- `check` (y `ShippedCatalogsTest`) avisan con `[xls]` si las claves del XLS importado difieren de
  las del XSD.

## Copia de validación de los XSD

`LibXmlSchemaValidator` no usa `resources/xsd` sino `resources/xsd/validacion`, que genera `write`:
los mismos archivos, salvo que en `catCFDI.xsd` los tipos de `ValidationSchemas::PATTERNS`
(c_ClaveProdServ, c_ClaveUnidad, c_CodigoPostal) se reducen a un patrón. Con las enumeraciones
completas libxml tarda ~0.5 s en cargar el esquema y unos 40 s en reportar una clave inexistente
(incluye las 95 000 claves en el mensaje). La existencia de esas claves la revisa `RuleCatalogos`.

`check` avisa con `[xsd]` y `ValidationSchemasTest` falla si la copia no coincide con lo que
generaría `write`.

## Piezas que se mantienen a mano

### `_excluded.php`

`catCFDI.xsd` conserva claves que el SAT ya dio de baja o que no acepta. Las que se listan aquí no
se escriben en `resources/catalogs` ni se reportan como "nuevas" en `check`. Antes de agregar o
quitar una, confirma su vigencia en `catCFDI.xls`.

| Catálogo | Claves | Motivo |
|---|---|---|
| c_UsoCFDI | P01 | "Por definir", herencia de CFDI 3.3; no existe en 4.0 |
| c_RegimenFiscal | 609, 628, 629, 630 | Están en el XSD pero no en el XLS vigente |
| c_TipoRelacion | 08, 09 | Están en el XSD pero el XLS 4.0 solo tiene 01–07 |

### `Domain/Catalogs/Claves/`

Las reglas nunca escriben claves literales (`'PUE'`, `'616'`, `'99'`…): usan constantes con nombre,
p. ej. `MetodoPago::PUE`, `RegimenFiscal::SIN_OBLIGACIONES_FISCALES`, `FormaPago::POR_DEFINIR`.
Así, para saber qué reglas dependen de una clave basta con buscar la constante.

- Cada clase declara `CATALOGO`, el catálogo al que pertenecen sus claves.
- `ClavesSat` registra las clases. `bin/cfdi-catalogs check` y `ClavesSatTest` verifican que **cada
  constante siga existiendo** en su catálogo; si el SAT retira una, sale el aviso `[claves]`.
- Para una clave nueva que una regla necesite: agrégala a la clase de su catálogo (o crea la clase y
  regístrala en `ClavesSat::CLASES`).
- Algunas clases agrupan claves por significado, p. ej. `TipoDeComprobante::sinFormaPago()` (T, N, P)
  y `sinMetodoPago()` (T, P), tomadas de la matriz de errores.

### `UsoCfdiMatrix`

La relación UsoCFDI × tipo de persona × RegimenFiscalReceptor solo existe en `catCFDI.xls`. La usa
`RuleRegimenUsoCfdi`. `check` detecta usos nuevos sin fila, usos retirados y regímenes inexistentes,
pero **no puede detectar** que el SAT cambie qué regímenes admite un uso: revísala contra el XLS
en cada actualización.

## Cómo actualizar los catálogos

```bash
# 1. Compara contra el XSD vigente del SAT (descarga temporal):
php bin/cfdi-catalogs check --fetch

# 2. Si hay diferencias, reemplaza el XSD de referencia y regenera claves y copia de validación:
curl -o resources/xsd/catCFDI.xsd http://www.sat.gob.mx/sitio_internet/cfd/catalogos/catCFDI.xsd
php bin/cfdi-catalogs write

# 3. Descarga el catCFDI.xls vigente (enlace en la página del Anexo 20) e importa sus columnas
#    (requiere LibreOffice; o --csv=carpeta si ya lo convertiste):
php bin/cfdi-catalogs xls --xls=catCFDI_V_4_AAAAMMDD.xls

# 4. Atiende los avisos que no se resuelven solos:
#    [matriz]  -> UsoCfdiMatrix.php (contra catCFDI.xls)
#    [claves]  -> Domain/Catalogs/Claves y las reglas que usan esa constante
#    [xls]     -> clave en el XSD sin fila en el XLS (o al revés): ¿falta excluirla en _excluded.php?
#    "+ X (nuevo en el SAT)" de algo que el SAT ya no acepta -> _excluded.php

# 5. Verifica:
composer check
```

`check` termina con código 1 si hay diferencias; `ShippedCatalogsTest` y `ValidationSchemasTest`
fallan si `resources/` se desfasa de los XSD de `resources/xsd`.

Si el SAT cambia `cfdv40.xsd` o `tdCFDI.xsd`, reemplázalos en `resources/xsd` y ejecuta `write`
para regenerar la copia de validación.

## Agregar un catálogo embebido

1. Agrega la constante en `CatalogName` (el valor es el nombre exacto del `xs:simpleType` en el XSD).
2. Ejecuta `php bin/cfdi-catalogs write`: crea `resources/catalogs/c_<Nombre>.php`.
3. Agrega la línea correspondiente en `RuleCatalogos` para el campo que valida.
4. Si es grande (miles de claves) y `cfdv40.xsd` lo usa, agrega su patrón en
   `ValidationSchemas::PATTERNS` y vuelve a ejecutar `write`.
5. Si alguna regla necesita claves concretas, crea su clase en `Claves/` y regístrala en `ClavesSat`.
6. Si necesita columnas del XLS: léelas en `CatalogXlsReader`, regístralas en `XlsCatalogs` e
   impórtalas con `bin/cfdi-catalogs xls`.
7. Actualiza las tablas de este documento.
