# spl/cfdi-core

Generación de CFDI 4.0 (facturas electrónicas del SAT) en PHP, independiente de framework.

A partir de los datos del comprobante, la biblioteca **calcula** importes, impuestos y totales con
aritmética decimal exacta, **valida** contra las reglas del Anexo 20, los catálogos del SAT y el
XSD, y **genera** el XML. Los errores nunca se lanzan como excepciones: se devuelven todos juntos,
en español y con la ruta del campo, listos para mostrarse al usuario final.

El **sellado** (cadena original + CSD) y el **timbrado** (PAC) aún no forman parte del proyecto: el XML
que se obtiene está listo para sellarse.

## Requisitos

- PHP 8.1 o superior con `bcmath`, `dom` y `libxml`.
- Solo para actualizar los catálogos desde `catCFDI.xls`: LibreOffice (`libreoffice --headless`).

## Instalación

```bash
composer require spl/cfdi-core
```

## Uso

```php
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Models\{Comprobante, Concepto, Emisor, ImpuestoTrasladado, Receptor, TipoFactor};

$cfdi = CfdiFactory::create();

$resultado = $cfdi->generate(new Comprobante(
    version: '4.0',
    fecha: new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')),
    moneda: 'MXN',                 // los decimales salen de c_Moneda
    tipoDeComprobante: 'I',
    exportacion: '01',
    lugarExpedicion: '62000',
    emisor: new Emisor('EKU9003173C9', 'ESCUELA KEMPER URGATE', '601'),
    receptor: new Receptor('URE180429TM6', 'UNIVERSIDAD ROBOTICA ESPAÑOLA', '86991', '601', 'G03'),
    conceptos: [
        new Concepto(
            claveProdServ: '81112100', cantidad: '1', claveUnidad: 'E48', unidad: 'Servicio',
            descripcion: 'Soporte técnico', valorUnitario: '1000.00', objetoImp: '02',
            impuestosTrasladados: [new ImpuestoTrasladado('002', TipoFactor::Tasa, '0.160000')],
        ),
    ],
    metodoPago: 'PUE',
    formaPago: '03',
));

if (!$resultado->isValid()) {
    foreach ($resultado->violations as $v) {
        echo "{$v->campo}: {$v->message}\n";
    }
}

echo $resultado->calculado?->importes->total;   // "1160.00"
$xml = $resultado->xml;                          // null si hay violaciones
```

Con datos erróneos (régimen 605 en una persona moral, FormaPago 99 con PUE) el resultado trae:

```
FormaPago: Con MetodoPago PUE (pago en una sola exhibición) la FormaPago no puede ser "99" (Por definir): indica cómo se recibió el pago, o usa PPD si aún no se recibe.
Receptor.RegimenFiscalReceptor: El RegimenFiscal del Receptor "605" (Sueldos y Salarios e Ingresos Asimilados a Salarios) no aplica a una persona moral (RFC URE180429TM6).
Receptor.UsoCFDI: UsoCFDI "G03" no es válido para el RegimenFiscalReceptor "605".
```

Todos los importes se reciben y se devuelven como **cadenas** (`'1000.00'`, nunca `1000.0`) para no
perder precisión.

### `CfdiResult`

| Propiedad | Contenido |
|---|---|
| `violations` | `Violation[]`: `code` estable (p. ej. `tasa-o-cuota-invalida`), `message` para el usuario y `campo` (`Concepto[2].Traslado[1].TasaOCuota`) |
| `calculado` | Importes, impuestos por concepto y resumen; `null` solo si hay errores de formato |
| `xml` | El XML sin sellar; solo desde `generate()` y sin violaciones |

`validate()` hace lo mismo que `generate()` pero nunca devuelve XML: sirve para revisar un formulario
mientras se captura.

### Opciones de `CfdiFactory::create()`

| Parámetro | Para qué |
|---|---|
| `DecimalConfiguration $config` | Escalas de cálculo y de concepto (por defecto, las del SAT) |
| `Clock $clock` | Reloj para la ventana de 72 h de la Fecha (útil en pruebas) |
| `Catalogs $catalogs` | Catálogos propios en lugar de los embebidos |
| `TipoCambioFix $tipoCambioFix` | Implementación que consulte el FIX del Banxico: con ella se revisa que el TipoCambio esté dentro del rango de variación de c_Moneda |
| `bool $validarXsd` | `false` omite la validación contra el XSD |

## Qué incluye

- Comprobante, InformacionGlobal, CfdiRelacionados, Emisor, Receptor, Conceptos e Impuestos.
- Traslados Tasa, Cuota (IEPS por cuota) y Exento; retenciones; IEPS incluido en la base del IVA.
- Más de 50 validaciones: formato, reglas del SAT (ObjetoImp, c_TasaOCuota, MetodoPago/FormaPago,
  régimen por tipo de persona, RFC genérico, TipoCambio…) y el XSD con mensajes en español.
- 15 catálogos del SAT embebidos y una herramienta para detectar cuándo se desfasan.

Detalle y pendientes en [`docs/funcionalidades.md`](docs/funcionalidades.md).

## Catálogos del SAT

Viajan embebidos en `resources/catalogs` y se regeneran con `bin/cfdi-catalogs`:

```bash
php bin/cfdi-catalogs check --fetch   # ¿cambió el catCFDI.xsd del SAT?
php bin/cfdi-catalogs write           # regenera claves y la copia de validación del XSD
php bin/cfdi-catalogs xls --xls=catCFDI.xls   # decimales, tipo de persona, c_TasaOCuota
```

Qué se genera, qué se mantiene a mano y el procedimiento completo: [`docs/catalogos.md`](docs/catalogos.md).

## Desarrollo

```bash
composer install
composer check   # PHPStan (nivel máximo) + pruebas + catálogos al día
```

Por separado: `composer analyse`, `composer test`, `composer catalogs`.

## Arquitectura

| Capa | Contenido |
|---|---|
| `Domain/Models` | Datos de entrada (`Comprobante`, `Concepto`…) y resultado del cálculo |
| `Domain/Rules` | `FormatRuleSet` (antes de calcular) y `RuleSet` (reglas del SAT); una clase por regla |
| `Domain/Catalogs` | Catálogos y claves con nombre (`Claves\MetodoPago::PPD`…) |
| `Domain/Services` | `InvoiceCalculator` |
| `Infrastructure` | bcmath, generador de XML, validación XSD, carga e importación de catálogos |
| `Application` | `CfdiService` y `CfdiResult` |
| `CfdiFactory` | Punto de entrada: arma todo con los valores por defecto |
