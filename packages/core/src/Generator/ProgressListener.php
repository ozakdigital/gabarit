<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator;

/**
 * Reçoit l'avancement de la génération (affichage CLI, statut du job côté web).
 */
interface ProgressListener
{
    public function stepStarted(GeneratorStep $step, int $index, int $total): void;

    public function stepFinished(GeneratorStep $step, int $index, int $total): void;
}
