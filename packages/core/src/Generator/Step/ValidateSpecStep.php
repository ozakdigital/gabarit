<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;
use Gabarit\Core\Matrix\SpecValidator;

/**
 * 1. Contrôle de la spec contre la matrice.
 */
final class ValidateSpecStep implements GeneratorStep
{
    public function name(): string
    {
        return 'validate';
    }

    public function label(): string
    {
        return 'Validation des choix';
    }

    public function run(GenerationContext $context): void
    {
        (new SpecValidator($context->matrix))->assertValid($context->spec);
    }
}
