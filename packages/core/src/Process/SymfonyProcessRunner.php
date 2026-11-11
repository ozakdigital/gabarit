<?php

declare(strict_types=1);

namespace Gabarit\Core\Process;

use Gabarit\Core\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final readonly class SymfonyProcessRunner implements ProcessRunner
{
    /**
     * @param (\Closure(string $type, string $buffer): void)|null $output reçoit la sortie en direct (mode verbeux)
     */
    public function __construct(
        private float $defaultTimeout = 900.0,
        private ?\Closure $output = null,
    ) {
    }

    public function run(array $command, string $cwd, array $env = [], ?float $timeout = null): string
    {
        $timeout ??= $this->defaultTimeout;
        $process = new Process($command, $cwd, $env + [
            'COMPOSER_NO_INTERACTION' => '1',
            // Composer a sa propre limite (300 s) pour ses scripts : on l'aligne sur la nôtre.
            'COMPOSER_PROCESS_TIMEOUT' => (string) (int) $timeout,
            'CI' => '1',
        ], null, $timeout);
        $process->run($this->output);

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($command, $process->getExitCode() ?? -1, $process->getErrorOutput().$process->getOutput());
        }

        return $process->getOutput();
    }

    public function has(string $executable): bool
    {
        return null !== (new ExecutableFinder())->find($executable);
    }
}
