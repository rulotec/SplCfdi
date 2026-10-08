<?php
declare(strict_types = 1);
namespace SplCfdi\Domain\Models;

final class ConceptoCalculado
{

    /** @param ImpuestoTrasladadoCalculado[] $traslados */
    public function __construct(public readonly Concepto $concepto, public readonly string $importe, public readonly array $traslados)
    {
    }
}
