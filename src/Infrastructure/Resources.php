<?php

declare(strict_types=1);

namespace SplCfdi\Infrastructure;

/** Única referencia a la carpeta resources/ del paquete. */
final class Resources
{
    public static function path(string $relative): string
    {
        return dirname(__DIR__, 2) . '/resources/' . ltrim($relative, '/');
    }
}
