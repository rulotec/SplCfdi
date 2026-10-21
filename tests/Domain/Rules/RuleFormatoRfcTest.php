<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\{Emisor, Receptor};
use SplCfdi\Domain\Rules\RuleFormatoRfc;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Tests\Support\AssertsRules;
use SplCfdi\Tests\Support\Fixtures;

final class RuleFormatoRfcTest extends TestCase
{
    use AssertsRules;

    #[DataProvider('validos')]
    public function testAceptaRfcValidos(string $rfc): void
    {
        $this->assertCodigos([], $this->check($rfc, 'AAA010101AAA'));
    }

    public static function validos(): array
    {
        return [
            'moral'              => ['EKU9003173C9'],
            'física'             => ['GOMC800101AB1'],
            'público en general' => ['XAXX010101000'],
            'extranjero'         => ['XEXX010101000'],
            '29 de febrero 2000' => ['AAA000229AAA'],
            'con Ñ y &'          => ['Ñ&A010101AAA'],
        ];
    }

    #[DataProvider('invalidos')]
    public function testRechazaRfcInvalidos(string $rfc): void
    {
        $this->assertCodigos(['rfc-formato'], $this->check($rfc, 'AAA010101AAA'));
    }

    public static function invalidos(): array
    {
        return [
            'minúsculas'         => ['aaa010101aaa'],
            'once caracteres'    => ['AAA010101AA'],
            'catorce caracteres' => ['GOMC800101AB12'],
            'mes 13'             => ['AAA011301AAA'],
            'día 32'             => ['AAA010132AAA'],
            '30 de febrero'      => ['AAA010230AAA'],
            '29 feb sin bisiesto' => ['AAA010229AAA'],
            'vacío'              => [''],
            'espacio'            => ['AAA010101AA '],
        ];
    }

    public function testValidaTambienElRfcDelEmisor(): void
    {
        $this->assertCodigos(['rfc-formato'], $this->check('XAXX010101000', 'BAD'));
        $this->assertCodigos(['rfc-formato', 'rfc-formato'], $this->check('BAD', 'BAD'));
    }

    /** @return Violation[] */
    private function check(string $rfcReceptor, string $rfcEmisor): array
    {
        $c = Fixtures::comprobanteCon(
            [Fixtures::concepto('100.00')],
            new Receptor($rfcReceptor, 'RECEPTOR', '62000', '616', 'S01'),
            null,
            emisor: new Emisor($rfcEmisor, 'EMISOR', '601'),
        );

        return (new RuleFormatoRfc())->check($c);
    }
}
