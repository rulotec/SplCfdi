<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Cli;

use SplCfdi\Domain\Contracts\XmlSchemaValidator;
use SplCfdi\Domain\Rules\Violation;

/**
 * `bin/cfdi-validate`: valida archivos XML de CFDI contra los XSD (cfdv40 y addendas), sin las reglas del SAT.
 *
 * Códigos de salida: 0 todos válidos, 1 alguno tiene errores, 2 error de uso o archivo ilegible.
 */
final class ValidateXsdCommand
{
    public const USAGE = <<<TXT
        Uso: php bin/cfdi-validate <archivo.xml>...

          Valida cada CFDI 4.0 contra cfdv40.xsd y sus addendas contra el XSD de cada una.
          No revisa las reglas del SAT (catálogos, importes, RFC…), ni los complementos (TimbreFiscalDigital…).

        TXT;

    private const OK = 0;
    private const INVALID = 1;
    private const USAGE_ERROR = 2;

    public function __construct(private readonly XmlSchemaValidator $validator) {}

    /**
     * @param string[] $args argumentos sin el nombre del programa
     * @param resource $out
     * @param resource $err
     */
    public function run(array $args, $out, $err): int
    {
        if (array_intersect($args, ['-h', '--help']) !== []) {
            fwrite($out, self::USAGE);

            return self::OK;
        }
        if ($args === []) {
            fwrite($err, self::USAGE);

            return self::USAGE_ERROR;
        }

        $codigo = self::OK;
        foreach ($args as $path) {
            $xml = is_file($path) ? @file_get_contents($path) : false;
            if ($xml === false) {
                fwrite($err, "No se pudo leer $path\n");
                $codigo = self::USAGE_ERROR;
                continue;
            }

            $violaciones = $this->validator->validate($xml);
            fwrite($out, self::reporte($path, $violaciones));
            if ($violaciones !== [] && $codigo === self::OK) {
                $codigo = self::INVALID;
            }
        }

        return $codigo;
    }

    /** @param Violation[] $violaciones */
    private static function reporte(string $path, array $violaciones): string
    {
        if ($violaciones === []) {
            return "$path: válido\n";
        }

        $lineas = [sprintf('%s: %d %s', $path, count($violaciones), count($violaciones) === 1 ? 'error' : 'errores')];
        foreach ($violaciones as $v) {
            $lineas[] = '  ' . ($v->campo === null ? '' : "{$v->campo}: ") . "{$v->message} [{$v->code}]";
        }

        return implode("\n", $lineas) . "\n";
    }
}
