<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Cli;

use PHPUnit\Framework\TestCase;
use SplCfdi\CfdiFactory;
use SplCfdi\Domain\Contracts\XmlSchemaValidator;
use SplCfdi\Domain\Rules\Violation;
use SplCfdi\Infrastructure\Cli\ValidateXsdCommand;

final class ValidateXsdCommandTest extends TestCase
{
    private const VALIDO = __DIR__ . '/../../Fixtures/addendas/METALSA.xml';

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    protected function setUp(): void
    {
        $this->out = $this->stream();
        $this->err = $this->stream();
    }

    public function testUnCfdiTimbradoConAddendaEsValido(): void
    {
        $codigo = $this->ejecutar(new ValidateXsdCommand(CfdiFactory::createSchemaValidator()), [self::VALIDO]);

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('METALSA.xml: válido', $this->leer($this->out));
    }

    public function testReportaCadaViolacionConSuCampoYCodigo(): void
    {
        $command = new ValidateXsdCommand($this->validador([
            new Violation('xsd-longitud', 'NoCertificado debe tener 20 caracteres.', 'NoCertificado'),
            new Violation('xml-vacio', 'El XML está vacío.'),
        ]));

        $codigo = $this->ejecutar($command, [self::VALIDO]);

        $this->assertSame(1, $codigo);
        $salida = $this->leer($this->out);
        $this->assertStringContainsString('METALSA.xml: 2 errores', $salida);
        $this->assertStringContainsString('  NoCertificado: NoCertificado debe tener 20 caracteres. [xsd-longitud]', $salida);
        $this->assertStringContainsString('  El XML está vacío. [xml-vacio]', $salida);
    }

    public function testValidaVariosArchivosYFallaSiAlgunoTieneErrores(): void
    {
        $validos = new ValidateXsdCommand($this->validador([]));
        $this->assertSame(0, $this->ejecutar($validos, [self::VALIDO, self::VALIDO]));
        $this->assertSame(2, substr_count($this->leer($this->out), ': válido'));

        $invalido = new ValidateXsdCommand($this->validador([new Violation('x', 'mal')]));
        $this->assertSame(1, $this->ejecutar($invalido, [self::VALIDO, self::VALIDO]));
    }

    public function testUnArchivoQueNoExisteEsUnErrorDeUso(): void
    {
        $codigo = $this->ejecutar(new ValidateXsdCommand($this->validador([])), ['/no/existe.xml']);

        $this->assertSame(2, $codigo);
        $this->assertStringContainsString('No se pudo leer /no/existe.xml', $this->leer($this->err));
    }

    public function testSinArchivosMuestraElUso(): void
    {
        $codigo = $this->ejecutar(new ValidateXsdCommand($this->validador([])), []);

        $this->assertSame(2, $codigo);
        $this->assertStringContainsString('Uso:', $this->leer($this->err));
    }

    public function testAyuda(): void
    {
        $this->assertSame(0, $this->ejecutar(new ValidateXsdCommand($this->validador([])), ['--help']));
        $this->assertStringContainsString('Uso:', $this->leer($this->out));
    }

    /** @param string[] $args */
    private function ejecutar(ValidateXsdCommand $command, array $args): int
    {
        return $command->run($args, $this->out, $this->err);
    }

    /** @param Violation[] $violaciones */
    private function validador(array $violaciones): XmlSchemaValidator
    {
        return new class ($violaciones) implements XmlSchemaValidator {
            /** @param Violation[] $violaciones */
            public function __construct(private readonly array $violaciones) {}

            public function validate(string $xml): array
            {
                return $this->violaciones;
            }
        };
    }

    /** @return resource */
    private function stream()
    {
        $stream = fopen('php://memory', 'w+');
        self::assertNotFalse($stream);

        return $stream;
    }

    /** @param resource $stream */
    private function leer($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
