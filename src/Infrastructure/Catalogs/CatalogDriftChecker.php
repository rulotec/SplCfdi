<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure\Catalogs;

use SplCfdi\Domain\Catalogs\CatalogName;
use SplCfdi\Domain\Catalogs\UsoCfdiMatrix;

final class CatalogDriftChecker
{
    /**
     * @param array<string, string[]> $embedded catálogos embebidos, por nombre
     * @param array<string, string[]> $xsd      valores leídos del XSD, por nombre
     *
     * @return CatalogDrift[] solo los catálogos con diferencias
     */
    public function compare(array $embedded, array $xsd): array
    {
        $drifts = [];

        foreach ($embedded as $name => $values) {
            $name = (string) $name;

            if (!isset($xsd[$name])) {
                $drifts[] = new CatalogDrift($name, [], [], true);
                continue;
            }

            $added = array_values(array_diff($xsd[$name], $values));
            $removed = array_values(array_diff($values, $xsd[$name]));

            if ($added !== [] || $removed !== []) {
                sort($added);
                sort($removed);
                $drifts[] = new CatalogDrift($name, $added, $removed);
            }
        }

        return $drifts;
    }

    /**
     * Contrasta UsoCfdiMatrix con las listas de usos y regímenes del XSD.
     *
     * @param array<string, string[]> $xsd
     *
     * @return string[] mensajes; vacío si la matriz es coherente con el XSD
     */
    public function matrixIssues(array $xsd): array
    {
        $issues = [];
        $usos = $xsd[CatalogName::USO_CFDI] ?? null;
        $regimenes = $xsd[CatalogName::REGIMEN_FISCAL] ?? null;

        if ($usos !== null) {
            foreach (array_diff($usos, UsoCfdiMatrix::usos()) as $uso) {
                $issues[] = "UsoCFDI $uso está en c_UsoCFDI pero no en UsoCfdiMatrix: no se valida contra régimen ni persona.";
            }
            foreach (array_diff(UsoCfdiMatrix::usos(), $usos) as $uso) {
                $issues[] = "UsoCfdiMatrix tiene el uso $uso, que ya no está en c_UsoCFDI.";
            }
        }

        if ($regimenes !== null) {
            foreach (array_diff(UsoCfdiMatrix::regimenes(), $regimenes) as $regimen) {
                $issues[] = "UsoCfdiMatrix cita el régimen $regimen, que no está en c_RegimenFiscal.";
            }
        }

        return $issues;
    }
}
