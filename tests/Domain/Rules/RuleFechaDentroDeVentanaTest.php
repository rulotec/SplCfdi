<?php
// tests/Domain/Rules/FechaDentroDeVentanaTest.php
declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\Comprobante;
use SplCfdi\Domain\Rules\RuleFechaDentroDeVentana;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\FixedClock;
use SplCfdi\Tests\Support\Fixtures;

final class RuleFechaDentroDeVentanaTest extends TestCase
{
    use AssertsRules;

    // La Fecha de los comprobantes de prueba es 2026-10-01T12:00:00

    #[DataProvider('casos')]
    public function testVentanaDe72Horas(string $ahora, ?string $codigo): void
    {
        $comprobante = Fixtures::comprobante([Fixtures::concepto('100.00')]);

        $this->assertCodigos($codigo === null ? [] : [$codigo], $this->violaciones($ahora, $comprobante));
    }

    public static function casos(): array
    {
        return [
            'una hora después'        => ['2026-10-01T13:00:00', null],
            'justo 72 horas'          => ['2026-10-04T12:00:00', null],
            '72 horas y un segundo'   => ['2026-10-04T12:00:01', 'fecha-vencida'],
            'un segundo en el futuro' => ['2026-10-01T11:59:59', 'fecha-futura'],
        ];
    }

    /** 18:00 UTC equivale a 12:00 en México, pero el XML diría "18:00": hora futura para el SAT. */
    public function testInterpretaLaHoraDelXmlEnLaZonaDelReloj(): void
    {
        $fecha = new DateTimeImmutable('2026-10-01T18:00:00', new DateTimeZone('UTC'));
        $comprobante = Fixtures::comprobante([Fixtures::concepto('100.00')], fecha: $fecha);

        $this->assertCodigos(['fecha-futura'], $this->violaciones('2026-10-01T13:00:00', $comprobante));
    }

    /** @return Violation[] */
    private function violaciones(string $ahora, Comprobante $comprobante): array
    {
        $reloj = new FixedClock(new DateTimeImmutable($ahora, new DateTimeZone('America/Mexico_City')));

        return (new RuleFechaDentroDeVentana($reloj))->check(Fixtures::calculado($comprobante));
    }
}
