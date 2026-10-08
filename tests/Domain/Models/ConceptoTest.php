<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Models;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Tests\Support\Fixtures;

final class ConceptoTest extends TestCase
{
    #[DataProvider('invalidos')]
    public function testRechazaCantidadYValorUnitarioInvalidos(string $valorUnitario, string $cantidad): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Fixtures::concepto($valorUnitario, '0.160000', $cantidad);
    }

    public static function invalidos(): array
    {
        return [
            'cantidad con 7 decimales'       => ['10.00', '1.1234567'],
            'valor unitario con 7 decimales' => ['10.1234567', '1'],
            'cantidad no numérica'           => ['10.00', 'abc'],
            'cantidad negativa'              => ['10.00', '-1'],
            'valor unitario vacío'           => ['', '1'],
        ];
    }
}
