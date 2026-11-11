<?php

declare(strict_types=1);

namespace Gabarit\Cli\Console;

use Gabarit\Core\Generator\GeneratorStep;
use Gabarit\Core\Generator\ProgressListener;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ConsoleProgressListener implements ProgressListener
{
    public function __construct(private OutputInterface $output)
    {
    }

    public function stepStarted(GeneratorStep $step, int $index, int $total): void
    {
        $this->output->writeln(\sprintf(' <comment>[%d/%d]</comment> %s…', $index, $total, $step->label()));
    }

    public function stepFinished(GeneratorStep $step, int $index, int $total): void
    {
    }
}
