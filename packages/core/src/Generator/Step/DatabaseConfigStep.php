<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;

/**
 * 4. Configuration de la base (non secrète) et test de santé /api/health, selon le framework.
 * Les mots de passe sont écrits par SecretsStep, dans un fichier non versionné.
 */
final class DatabaseConfigStep implements GeneratorStep
{
    public function name(): string
    {
        return 'database';
    }

    public function label(): string
    {
        return 'Configuration de la base de données';
    }

    public function run(GenerationContext $context): void
    {
        $context->profile()->configureDatabase($context);
    }
}
