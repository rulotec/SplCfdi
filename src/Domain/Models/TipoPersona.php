<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Models;

enum TipoPersona: string
{
    case Fisica = 'física';
    case Moral = 'moral';

    /** Por longitud del RFC: 12 caracteres = moral, 13 = física; otra longitud la reporta RuleFormatoRfc. */
    public static function deRfc(string $rfc): ?self
    {
        return match (mb_strlen($rfc)) {
            12 => self::Moral,
            13 => self::Fisica,
            default => null,
        };
    }
}
