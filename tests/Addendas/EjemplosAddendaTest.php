<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Addendas;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Addendas\AddendaRegistry;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\EjemplosAddenda;

/** Con los datos de cada CFDI real se genera la misma addenda, válida contra su XSD. */
final class EjemplosAddendaTest extends TestCase
{
    /** @return array<string, array{string, Comprobante, array<string, string>, AddendaRegistry}> */
    public static function ejemplos(): array
    {
        $casos = [];
        foreach (EjemplosAddenda::todos() as $archivo => [$comprobante, $diferencias, $addendas]) {
            $casos[$archivo] = [$archivo, $comprobante, $diferencias, $addendas];
        }

        return $casos;
    }

    /** @param array<string, string> $diferencias */
    #[DataProvider('ejemplos')]
    public function testGeneraLaAddendaDelEjemplo(string $archivo, Comprobante $comprobante, array $diferencias, AddendaRegistry $addendas): void
    {
        $resultado = CfdiFactory::create(null, EjemplosAddenda::clock($comprobante), addendas: $addendas)->generate($comprobante);

        $this->assertSame([], array_map(fn (Violation $v) => "{$v->code} {$v->campo}: {$v->message}", $resultado->violations));

        $esperada = EjemplosAddenda::addendaCanonica(EjemplosAddenda::xml($archivo));
        foreach ($diferencias as $ejemplo => $generado) {
            $this->assertSame(1, substr_count($esperada, $ejemplo), "El ejemplo debe contener una vez: $ejemplo");
            $esperada = str_replace($ejemplo, $generado, $esperada);
        }
        $this->assertSame($esperada, EjemplosAddenda::addendaCanonica((string) $resultado->xml));
    }
}
