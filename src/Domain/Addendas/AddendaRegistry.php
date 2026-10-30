<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Addendas;

use SplCfdi\Addendas\{AmericanAxleIex, StellantisPpy, VolkswagenPsv};
use SplCfdi\Domain\Contracts\DecimalMath;

/** Las addendas disponibles, por clave. */
final class AddendaRegistry
{
    /** @var array<string, Addenda> */
    private readonly array $addendas;

    public function __construct(Addenda ...$addendas)
    {
        $porClave = [];
        foreach ($addendas as $addenda) {
            if (isset($porClave[$addenda->clave()])) {
                throw new \InvalidArgumentException("Hay dos addendas con la clave {$addenda->clave()}.");
            }
            $porClave[$addenda->clave()] = $addenda;
        }
        $this->addendas = $porClave;
    }

    /** Las que incluye la biblioteca. Una addenda nueva se agrega aquí. */
    public static function standard(DecimalMath $math): self
    {
        return new self(
            new AmericanAxleIex($math),
            new StellantisPpy($math),
            new VolkswagenPsv($math),
        );
    }

    /** Copia con una addenda más (o que reemplaza a la de la misma clave): así un host agrega las suyas. */
    public function con(Addenda $addenda): self
    {
        $addendas = $this->addendas;
        $addendas[$addenda->clave()] = $addenda;

        return new self(...array_values($addendas));
    }

    public function get(string $clave): ?Addenda
    {
        return $this->addendas[$clave] ?? null;
    }

    public function porNamespace(string $namespace): ?Addenda
    {
        foreach ($this->addendas as $addenda) {
            if ($addenda->namespace() === $namespace) {
                return $addenda;
            }
        }

        return null;
    }

    /** @return array<string, Addenda> clave => addenda */
    public function todas(): array
    {
        return $this->addendas;
    }
}
