<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;
use Symfony\Component\Yaml\Yaml;

/**
 * 6. Ajoute les services annexes au compose et leurs variables à l'environnement du framework.
 */
final class ServicesStep implements GeneratorStep
{
    public const string MARKER = '  # gabarit:services';

    public function name(): string
    {
        return 'services';
    }

    public function label(): string
    {
        return 'Services annexes';
    }

    public function run(GenerationContext $context): void
    {
        $compose = $context->read('compose.yaml');
        if (!str_contains($compose, self::MARKER)) {
            throw new GenerationException('Marqueur « # gabarit:services » absent de compose.yaml.');
        }

        $parsed = Yaml::parse($compose);
        $existing = \is_array($parsed) && \is_array($parsed['services'] ?? null) ? $parsed['services'] : [];

        foreach ($context->spec->services as $service) {
            if (!\array_key_exists($service->value, $existing)) {
                $fragment = $context->renderer->render('services/'.$service->value.'.yaml.twig', $context->templateContext());
                $compose = str_replace(self::MARKER, "\n".rtrim($fragment)."\n".self::MARKER, $compose);
            }
        }
        $context->write('compose.yaml', $compose);

        $context->profile()->configureServices($context);
    }
}
