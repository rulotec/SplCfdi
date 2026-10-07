<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Infrastructure\Xml;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Exceptions\XmlSchemaValidationException;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;

final class LibXmlSchemaValidatorTest extends TestCase
{
    private LibXmlSchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new LibXmlSchemaValidator();
    }

    public function testRechazaXmlMalFormado(): void
    {
        $this->expectException(XmlSchemaValidationException::class);
        $this->validator->validate('<cfdi:Comprobante>');
    }

    public function testRechazaUnaRaizQueNoEsComprobante(): void
    {
        $this->expectException(XmlSchemaValidationException::class);
        $this->validator->validate('<foo/>');
    }

    public function testRechazaXmlVacio(): void
    {
        $this->expectException(XmlSchemaValidationException::class);
        $this->validator->validate('');
    }

    public function testLaExcepcionTraeLaListaDeErrores(): void
    {
        try {
            $this->validator->validate('<foo/>');
            $this->fail('Se esperaba XmlSchemaValidationException.');
        } catch (XmlSchemaValidationException $e) {
            $this->assertNotEmpty($e->errors);
        }
    }

    public function testFallaSiNoExisteElXsd(): void
    {
        $this->expectException(\RuntimeException::class);
        (new LibXmlSchemaValidator('/ruta/que/no/existe.xsd'))->validate('<foo/>');
    }
}
