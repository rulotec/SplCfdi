<?php

declare(strict_types=1);

namespace SplCfdi\Tests\Domain\Models;

use PHPUnit\Framework\TestCase;
use SplCfdi\Domain\Models\Receptor;

final class ReceptorTest extends TestCase
{
    public function testRechazaUnDomicilioFiscalInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DomicilioFiscalReceptor');

        new Receptor('XAXX010101000', 'PUBLICO EN GENERAL', '6200', '616', 'S01');
    }
}