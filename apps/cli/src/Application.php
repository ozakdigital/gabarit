<?php

declare(strict_types=1);

namespace Gabarit\Cli;

use Gabarit\Cli\Command\DoctorCommand;
use Gabarit\Cli\Command\MatrixCommand;
use Gabarit\Cli\Command\NewCommand;
use Gabarit\Core\Matrix\Matrix;
use Gabarit\Core\Process\ProcessRunner;
use Symfony\Component\Console\Application as ConsoleApplication;

final class Application extends ConsoleApplication
{
    public const string VERSION = '0.1.0';

    public function __construct(Matrix $matrix, ProcessRunner $process)
    {
        parent::__construct('Gabarit', self::VERSION);

        $this->addCommands([
            new NewCommand($matrix, $process),
            new MatrixCommand($matrix),
            new DoctorCommand($process),
        ]);
    }
}
