<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

/**
 * Lee de catCFDI.xls (convertido a CSV, una hoja por archivo) las columnas que catCFDI.xsd no trae:
 * decimales y variación de c_Moneda, Física/Moral de c_RegimenFiscal y la tabla c_TasaOCuota.
 *
 * Conversión: libreoffice --headless --convert-to 'csv:Text - txt - csv (StarCalc):44,34,76,1,,0,false,true,false,false,false,-1' catCFDI.xls
 * (`bin/cfdi-catalogs xls --xls=…` la hace sola). Cada hoja queda en "<archivo>-<hoja>.csv".
 */
final class CatalogXlsReader
{
    /** Nombres de impuesto que usa la hoja c_TasaOCuota → clave de c_Impuesto. */
    private const IMPUESTOS = ['ISR' => '001', 'IVA' => '002', 'IEPS' => '003'];

    public function __construct(private readonly string $csvDir, private readonly \DateTimeImmutable $hoy) {}

    /** @return array<string, array{descripcion: string, decimales: int, variacion: ?string}> */
    public function monedas(): array
    {
        $monedas = [];
        foreach ($this->filas('c_Moneda', 'c_Moneda') as $f) {
            if (!$this->vigente($f[5] ?? '')) {
                continue;
            }
            $variacion = rtrim(trim($f[3] ?? ''), '%');
            $monedas[$f[0]] = [
                'descripcion' => trim($f[1] ?? ''),
                'decimales' => (int) ($f[2] ?? 0),
                'variacion' => is_numeric($variacion) ? bcdiv($variacion, '100', 4) : null,
            ];
        }

        return $monedas;
    }

    /** @return array<int|string, array{descripcion: string, fisica: bool, moral: bool}> claves numéricas: PHP las vuelve int */
    public function regimenesFiscales(): array
    {
        $regimenes = [];
        foreach ($this->filas('c_RegimenFiscal', 'c_RegimenFiscal') as $f) {
            if (!$this->vigente($f[5] ?? '')) {
                continue;
            }
            $regimenes[$f[0]] = [
                'descripcion' => trim($f[1] ?? ''),
                'fisica' => $this->siNo($f[2] ?? ''),
                'moral' => $this->siNo($f[3] ?? ''),
            ];
        }

        return $regimenes;
    }

    /**
     * Se conservan las fechas de vigencia: la regla filtra por la Fecha del comprobante,
     * así el cambio anual de la cuota del IEPS no depende de cuándo se importó.
     *
     * @return list<array{impuesto: string, factor: string, minimo: ?string, maximo: string, traslado: bool, retencion: bool, inicio: string, fin: ?string}>
     */
    public function tasasOCuotas(): array
    {
        $tasas = [];
        foreach ($this->filas('c_TasaOCuota', 'Rango o Fijo') as $f) {
            if (($f[0] ?? '') !== 'Rango' && ($f[0] ?? '') !== 'Fijo') {
                continue;   // la fila de subtítulos "Valor mínimo / Valor máximo"
            }
            $nombre = trim($f[3] ?? '');
            $impuesto = self::IMPUESTOS[strtok($nombre, ' ') ?: ''] ?? throw new \RuntimeException(
                "c_TasaOCuota: impuesto desconocido \"$nombre\"; agrégalo a CatalogXlsReader::IMPUESTOS."
            );
            $fin = $this->fecha($f[8] ?? '');

            $tasas[] = [
                'impuesto' => $impuesto,
                'factor' => trim($f[4] ?? ''),
                'minimo' => $f[0] === 'Rango' ? $this->decimal($f[1] ?? '') : null,
                'maximo' => $this->decimal($f[2] ?? ''),
                'traslado' => $this->siNo($f[5] ?? ''),
                'retencion' => $this->siNo($f[6] ?? ''),
                'inicio' => $this->fecha($f[7] ?? '') ?? '2022-01-01',
                'fin' => $fin,
            ];
        }

        return $tasas;
    }

    /**
     * Filas de datos de una hoja: las que siguen al encabezado cuya primera celda es $encabezado.
     *
     * @return list<list<string>>
     */
    private function filas(string $hoja, string $encabezado): array
    {
        $archivos = glob(rtrim($this->csvDir, '/') . "/*-$hoja.csv") ?: [];
        if (count($archivos) !== 1) {
            throw new \RuntimeException("No se encontró la hoja $hoja en {$this->csvDir} (se espera un archivo *-$hoja.csv).");
        }

        $handle = fopen($archivos[0], 'r') ?: throw new \RuntimeException("No se pudo abrir {$archivos[0]}.");
        $filas = [];
        $enDatos = false;

        while (($fila = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $fila = array_map(fn ($c) => trim((string) $c), $fila);
            if (!$enDatos) {
                $enDatos = $fila[0] === $encabezado;
                continue;
            }
            if (implode('', $fila) !== '') {
                $filas[] = $fila;
            }
        }
        fclose($handle);

        return $filas;
    }

    /** Las fechas del CSV vienen como m/d/Y. */
    private function fecha(string $valor): ?string
    {
        $fecha = \DateTimeImmutable::createFromFormat('!m/d/Y', trim($valor));

        return $fecha === false ? null : $fecha->format('Y-m-d');
    }

    private function vigente(string $finVigencia): bool
    {
        $fin = $this->fecha($finVigencia);

        return $fin === null || $fin >= $this->hoy->format('Y-m-d');
    }

    /** @return numeric-string */
    private function decimal(string $valor): string
    {
        if (!is_numeric($valor)) {
            throw new \RuntimeException("c_TasaOCuota: valor no numérico \"$valor\".");
        }

        return bcadd($valor, '0', 6);
    }

    private function siNo(string $valor): bool
    {
        return mb_strtolower(trim($valor)) === 'sí';
    }
}
