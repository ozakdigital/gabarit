<?php

declare(strict_types=1);

namespace Gabarit\Core\Process;

use Gabarit\Core\Exception\ProcessFailedException;

/**
 * Exécute une commande externe. Toujours un tableau d'arguments, jamais une chaîne shell.
 */
interface ProcessRunner
{
    /**
     * @param list<string>          $command
     * @param array<string, string> $env
     *
     * @return string sortie standard
     *
     * @throws ProcessFailedException
     */
    public function run(array $command, string $cwd, array $env = [], ?float $timeout = null): string;

    /**
     * Indique si un exécutable est disponible (pour `gabarit doctor`).
     */
    public function has(string $executable): bool;
}
