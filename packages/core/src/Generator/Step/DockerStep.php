<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;

/**
 * 5. Dockerfile, compose.yaml, nginx, .dockerignore.
 */
final class DockerStep implements GeneratorStep
{
    public function name(): string
    {
        return 'docker';
    }

    public function label(): string
    {
        return 'Fichiers Docker';
    }

    public function run(GenerationContext $context): void
    {
        $context->render('docker/Dockerfile.twig', 'Dockerfile');
        $context->render('docker/php.ini.twig', 'docker/php/app.ini');
        $context->render('docker/fpm.conf.twig', 'docker/php/fpm.conf');
        $context->render('docker/entrypoint.sh.twig', 'docker/php/entrypoint.sh');
        @chmod($context->path('docker/php/entrypoint.sh'), 0o755);
        $context->render('docker/nginx.conf.twig', 'docker/nginx/default.conf');
        $context->render('docker/compose.yaml.twig', 'compose.yaml');
        $context->render('docker/dockerignore.twig', '.dockerignore');
    }
}
