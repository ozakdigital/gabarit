<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator;

/**
 * Une couche du pipeline. Ne dépend que de la spec et de l'état du dossier cible ;
 * idempotente quand c'est possible.
 */
interface GeneratorStep
{
    /** Identifiant stable (suivi de progression, logs, API). */
    public function name(): string;

    /** Libellé affiché à l'utilisateur, en français. */
    public function label(): string;

    public function run(GenerationContext $context): void;
}
