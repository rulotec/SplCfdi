<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

final class Concepto
{
    /**
     * @var ImpuestoTrasladado[]
     */
    public readonly array $impuestosTrasladados;

    /**
     * @var ImpuestoTrasladadoCalculado[]|null
     */
    private ?array $impuestosTrasladadosCalculados = null;

    /**
     * @param ImpuestoTrasladado[] $impuestosTrasladados
     */
    public function __construct(
        public readonly string $claveProdServ,
        public readonly string $cantidad,
        public readonly string $claveUnidad,
        public readonly string $unidad,
        public readonly string $descripcion,
        public readonly string $valorUnitario,
        public readonly string $importe,
        public readonly string $objetoImp,
        array $impuestosTrasladados = []
    ) {
        $this->impuestosTrasladados = $impuestosTrasladados;
    }

    /**
     * @return ImpuestoTrasladadoCalculado[]|null
     */
    public function getImpuestosTrasladadosCalculados(): ?array
    {
        return $this->impuestosTrasladadosCalculados;
    }

    /**
     * @param ImpuestoTrasladadoCalculado[] $impuestos
     */
    public function setImpuestosTrasladadosCalculados(array $impuestos): void
    {
        if ($this->impuestosTrasladadosCalculados !== null) {
            throw new \LogicException(
                'Los impuestos calculados del concepto ya fueron establecidos.'
            );
        }

        $this->impuestosTrasladadosCalculados = $impuestos;
    }
}