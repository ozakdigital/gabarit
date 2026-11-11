<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator;

final class NullProgressListener implements ProgressListener
{
    public function stepStarted(GeneratorStep $step, int $index, int $total): void
    {
    }

    public function stepFinished(GeneratorStep $step, int $index, int $total): void
    {
    }
}
