<?php

declare(strict_types=1);

namespace Gabarit\Cli\Console;

/**
 * Codes de sortie de la CLI (CLAUDE.md §8).
 */
final class ExitCode
{
    public const int SUCCESS = 0;
    public const int VALIDATION_ERROR = 1;
    public const int RUNTIME_ERROR = 2;
}
