# spl/cfdi-core

Biblioteca PHP 8.1+ que calcula, valida (reglas del SAT, catálogos, XSD) y genera el XML de CFDI 4.0.
Sin dependencias de framework ni de vtiger. El README explica uso y arquitectura.

- `composer check`: PHPStan nivel máximo + PHPUnit + catálogos al día. Debe pasar antes de cada commit.
- Modelos inmutables; importes como cadenas numéricas y aritmética bcmath, nunca float.
- Los errores no son excepciones: `CfdiResult::$violations` con `code` estable, `message` en español para
  el usuario final y `campo` como ruta del XML (`Concepto[2].Traslado[1].TasaOCuota`, sin contenedores).
- `CfdiService`: `generate()` (XML solo si es válido), `validate()` (sin XML) y `draft()` (XML aunque
  haya violaciones, para mostrarlo con los errores marcados; nunca se sella).
- Aún no incluye sellado (CSD) ni timbrado (PAC).

Consumidor principal: el módulo de vtiger `../SplVtCfdi`, que la instala como repositorio composer `path`
copiado. Tras cambiar esta biblioteca, en `../SplVtCfdi/src/modules/SplVtCfdi`:
`rm -rf vendor/spl/cfdi-core && composer install`.
