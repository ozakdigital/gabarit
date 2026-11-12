<?php

declare(strict_types=1);

namespace Gabarit\Core;

/**
 * Identité de Gabarit, partagée par la CLI, le web et les projets « starter ».
 */
final class Gabarit
{
    public const string VERSION = '0.2.0';

    /** Image publiée par la CI (GitHub Container Registry), utilisée par `bin/up` des starters. */
    public const string IMAGE = 'ghcr.io/ozakdigital/gabarit';

    public static function image(): string
    {
        return self::IMAGE.':'.self::VERSION;
    }
}
