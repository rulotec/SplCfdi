<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Addendas;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Addendas\AddendaRegistry;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Infrastructure\Addendas\AddendaSchemaValidator;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Tests\Support\EjemplosAddenda;

final class AddendaSchemaValidatorTest extends TestCase
{
    private function validator(): AddendaSchemaValidator
    {
        return new AddendaSchemaValidator(AddendaRegistry::standard(new BcMathDecimalMath(DecimalConfiguration::sat())));
    }

    /** @return iterable<string, array{string}> */
    public static function ejemplos(): iterable
    {
        foreach (['AMERICAN_AXLE.xml', 'Stellantis.xml', 'VOLKSWAGEN.xml', 'METALSA.xml', 'MARELLI_MEXICANA.xml'] as $archivo) {
            yield $archivo => [$archivo];
        }
    }

    /** La de Marelli no está registrada: no se revisa. */
    #[DataProvider('ejemplos')]
    public function testLasAddendasDeLosEjemplosCumplenConSuEsquema(string $archivo): void
    {
        $this->assertSame([], $this->validator()->validate(EjemplosAddenda::xml($archivo)));
    }

    /** El generador anterior declaraba xsi con el namespace de XML Schema; la addenda generada no lo lleva. */
    public function testRechazaElSchemaLocationDelEjemploDeSanmina(): void
    {
        $violaciones = $this->validator()->validate(EjemplosAddenda::xml('SANMINA.xml'));

        $this->assertSame(['xsd-no-permitido'], array_map(fn (Violation $v) => $v->code, $violaciones));
        $this->assertStringContainsString('schemaLocation', $violaciones[0]->message);
    }

    public function testTraduceLosErroresConLaRutaDentroDeLaAddenda(): void
    {
        $xml = str_replace('posicion="1"', 'posicion="uno"', EjemplosAddenda::xml('VOLKSWAGEN.xml'));

        $violaciones = $this->validator()->validate($xml);

        $this->assertSame(['xsd-tipo'], array_map(fn (Violation $v) => $v->code, $violaciones));
        $this->assertSame('Addenda.Partes.Parte.posicion', $violaciones[0]->campo);
        $this->assertStringStartsWith('Addenda Volkswagen de México – Servicios (PSV), Partes, Parte: posicion "uno"', $violaciones[0]->message);
    }

    public function testReportaNodosFaltantes(): void
    {
        $xml = (string) preg_replace('/<PSV:Solicitante [^>]*\/>/', '', EjemplosAddenda::xml('VOLKSWAGEN.xml'));

        $violaciones = $this->validator()->validate($xml);

        $this->assertSame(['xsd-estructura'], array_map(fn (Violation $v) => $v->code, $violaciones));
        $this->assertStringContainsString('Solicitante', $violaciones[0]->message);
    }

    public function testNoRepiteElErrorDeUnXmlMalFormado(): void
    {
        $this->assertSame([], $this->validator()->validate('<cfdi:Comprobante'));
    }
}
