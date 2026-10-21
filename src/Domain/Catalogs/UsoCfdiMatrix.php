<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Catalogs;

/**
 * Matriz c_UsoCFDI × tipo de persona × c_RegimenFiscal (CFDI 4.0).
 *
 * Esta relación NO está en catCFDI.xsd (solo en catCFDI.xls): se mantiene a mano.
 * `php bin/cfdi-catalogs check` la contrasta con las listas de usos y regímenes del XSD,
 * pero no puede saber si cambió qué régimen admite cada uso.
 */
final class UsoCfdiMatrix
{
    private const REG_GENERAL = ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626'];
    private const REG_DEDUCCIONES = ['605', '606', '607', '608', '611', '612', '614', '615', '625'];
    private const REG_SIN_EFECTOS = [
        '601', '603', '605', '606', '607', '608', '610', '611', '612', '614', '615', '616',
        '620', '621', '622', '623', '624', '625', '626',
    ];

    /** @return array{fisica: bool, moral: bool, regimenes: string[]}|null */
    public static function find(string $uso): ?array
    {
        return self::table()[$uso] ?? null;
    }

    /** @return string[] usos con fila en la matriz */
    public static function usos(): array
    {
        return array_map('strval', array_keys(self::table()));
    }

    /** @return string[] regímenes citados en alguna fila, sin repetir */
    public static function regimenes(): array
    {
        $todos = [];
        foreach (self::table() as $fila) {
            foreach ($fila['regimenes'] as $regimen) {
                $todos[$regimen] = true;
            }
        }

        $lista = array_map('strval', array_keys($todos));
        sort($lista);

        return $lista;
    }

    /** @return array<string, array{fisica: bool, moral: bool, regimenes: string[]}> */
    private static function table(): array
    {
        $table = [];

        foreach (['G01', 'G02', 'G03', 'I01', 'I02', 'I03', 'I04', 'I05', 'I06', 'I07', 'I08'] as $uso) {
            $table[$uso] = ['fisica' => true, 'moral' => true, 'regimenes' => self::REG_GENERAL];
        }

        foreach (['D01', 'D02', 'D03', 'D05', 'D06', 'D07', 'D08', 'D09', 'D10'] as $uso) {
            $table[$uso] = ['fisica' => true, 'moral' => false, 'regimenes' => self::REG_DEDUCCIONES];
        }

        // Donativos: también personas morales (603).
        $table['D04'] = ['fisica' => true, 'moral' => true, 'regimenes' => [...self::REG_DEDUCCIONES, '603']];

        foreach (['S01', 'CP01'] as $uso) {
            $table[$uso] = ['fisica' => true, 'moral' => true, 'regimenes' => self::REG_SIN_EFECTOS];
        }

        $table['CN01'] = ['fisica' => true, 'moral' => false, 'regimenes' => ['605']];

        return $table;
    }
}
