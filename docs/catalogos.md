# Catálogos del SAT

Los catálogos del SAT (`catCFDI.xsd` / `catCFDI.xls`) son la principal fuente de cambios de este
proyecto: el SAT agrega, retira o reinterpreta claves varias veces al año. Este documento dice
**dónde vive cada pieza**, **qué se genera solo y qué se mantiene a mano**, y **cómo actualizar**.

## Resumen: dónde vive cada cosa

| Pieza | Ubicación | Origen | ¿Se edita a mano? |
|---|---|---|---|
| Lista de catálogos embebidos | `src/Domain/Catalogs/CatalogName.php` | — | Sí: una constante por catálogo |
| Valores de cada catálogo | `resources/catalogs/c_*.php` | `catCFDI.xsd` | **No**: los genera `bin/cfdi-catalogs write` |
| Claves que el XSD aún lista pero el SAT ya no acepta | `resources/catalogs/_excluded.php` | `catCFDI.xls` (fechas de vigencia) | Sí |
| Claves con nombre que usan las reglas | `src/Domain/Catalogs/Claves/*.php` | — | Sí |
| Matriz UsoCFDI × persona × régimen | `src/Domain/Catalogs/UsoCfdiMatrix.php` | `catCFDI.xls` (no está en el XSD) | Sí |
| XSD de catálogos de referencia | `resources/xsd/catCFDI.xsd` | Portal del SAT | Se reemplaza al actualizar |

## Catálogos embebidos

Son los catálogos pequeños, que se validan por existencia en `RuleCatalogos` (violación
`catalogo-invalido`).

| Catálogo | Campos del CFDI que valida | Claves con nombre |
|---|---|---|
| `c_TipoDeComprobante` | `TipoDeComprobante` | `Claves\TipoDeComprobante` |
| `c_Exportacion` | `Exportacion` | — |
| `c_MetodoPago` | `MetodoPago` | `Claves\MetodoPago` |
| `c_FormaPago` | `FormaPago` | — |
| `c_RegimenFiscal` | `Emisor.RegimenFiscal`, `Receptor.RegimenFiscalReceptor` | `Claves\RegimenFiscal` |
| `c_UsoCFDI` | `Receptor.UsoCFDI` | `Claves\UsoCfdi` |
| `c_Periodicidad` | `InformacionGlobal.Periodicidad` | `Claves\Periodicidad` |
| `c_Meses` | `InformacionGlobal.Meses` | `Claves\Meses` |
| `c_ObjetoImp` | `Concepto.ObjetoImp` | `Claves\ObjetoImp` |
| `c_Impuesto` | `Traslado.Impuesto`, `Retencion.Impuesto` | `Claves\Impuesto` |

Cada `resources/catalogs/c_<Nombre>.php` devuelve un arreglo de claves **como cadenas**
(`'01'`, nunca `1`: el cero a la izquierda es parte de la clave). `EmbeddedCatalogs` los carga y
`ArrayCatalog` los indexa.

### Catálogos que no se embeben

| Catálogo | Por qué | Cómo se valida hoy |
|---|---|---|
| `c_ClaveProdServ` (~50 000 claves) | Tamaño | Forma (8 dígitos) en `Concepto` + existencia vía XSD |
| `c_ClaveUnidad` | Tamaño | Solo existencia vía XSD |
| `c_CodigoPostal` (~95 000 claves) | Tamaño | Forma (5 dígitos) en `Comprobante` y `Receptor` + existencia vía XSD |
| `c_Moneda` | Los decimales por moneda no están en el XSD | Forma (3 letras) en `Moneda` + existencia vía XSD; los decimales los indica el host |
| `c_TasaOCuota` | Rangos y combinaciones impuesto/factor, no una lista simple | Forma (≤ 6 decimales) en los modelos de impuesto |

"Existencia vía XSD" significa `LibXmlSchemaValidator` contra `cfdv40.xsd` (que importa
`catCFDI.xsd`). Hoy **`CfdiService` no lo invoca**: el host debe llamarlo, y sus mensajes son los
de libxml, en inglés (ver `docs/funcionalidades.md`).

## Piezas que se mantienen a mano

### `_excluded.php`

`catCFDI.xsd` conserva claves que el SAT ya dio de baja (p. ej. `P01` "Por definir" de c_UsoCFDI,
herencia de CFDI 3.3). Las que se listan aquí no se escriben en `resources/catalogs` ni se reportan
como "nuevas" en `check`. Antes de agregar o quitar una, confirma su vigencia en las columnas de
fecha de `catCFDI.xls`.

### `Domain/Catalogs/Claves/`

Las reglas nunca escriben claves literales (`'PUE'`, `'616'`, `'05'`…): usan constantes con nombre,
p. ej. `MetodoPago::PUE`, `RegimenFiscal::SIN_OBLIGACIONES_FISCALES`, `Periodicidad::BIMESTRAL`.
Así, para saber qué reglas dependen de una clave basta con buscar la constante.

- Cada clase declara `CATALOGO`, el catálogo al que pertenecen sus claves.
- `ClavesSat` registra las clases. `bin/cfdi-catalogs check` y `ClavesSatTest` verifican que **cada
  constante siga existiendo** en su catálogo; si el SAT retira una, sale el aviso `[claves]`.
- Para una clave nueva que una regla necesite: agrégala a la clase de su catálogo (o crea la clase y
  regístrala en `ClavesSat::CLASES`).

### `UsoCfdiMatrix`

La relación UsoCFDI × tipo de persona × RegimenFiscalReceptor solo existe en `catCFDI.xls`. La usa
`RuleRegimenUsoCfdi`. `check` detecta usos nuevos sin fila, usos retirados y regímenes inexistentes,
pero **no puede detectar** que el SAT cambie qué regímenes admite un uso: revísala contra el XLS
en cada actualización.

## Cómo actualizar los catálogos

```bash
# 1. Compara contra el XSD vigente del SAT (descarga temporal):
php bin/cfdi-catalogs check --fetch

# 2. Si hay diferencias, reemplaza el XSD de referencia y regenera:
curl -o resources/xsd/catCFDI.xsd http://www.sat.gob.mx/sitio_internet/cfd/catalogos/catCFDI.xsd
php bin/cfdi-catalogs write

# 3. Atiende los avisos que no se resuelven solos:
#    [matriz]  -> UsoCfdiMatrix.php (contra catCFDI.xls)
#    [claves]  -> Domain/Catalogs/Claves y las reglas que usan esa constante
#    "- X (ya no está en el SAT)" de algo que el SAT sigue aceptando -> no aplica; si el XSD
#    lista algo que ya no se acepta -> _excluded.php

# 4. Verifica:
composer test
```

`check` termina con código 1 si hay diferencias (útil en CI); `ShippedCatalogsTest` falla si
`resources/catalogs` se desfasa del XSD de `resources/xsd`.

## Agregar un catálogo embebido

1. Agrega la constante en `CatalogName` (el valor es el nombre exacto del `xs:simpleType` en el XSD).
2. Ejecuta `php bin/cfdi-catalogs write`: crea `resources/catalogs/c_<Nombre>.php`.
3. Agrega la línea correspondiente en `RuleCatalogos` para el campo que valida.
4. Si alguna regla necesita claves concretas, crea su clase en `Claves/` y regístrala en `ClavesSat`.
5. Actualiza la tabla de este documento.
