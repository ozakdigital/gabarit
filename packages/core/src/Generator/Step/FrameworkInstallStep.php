<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;

/**
 * 2. Installation officielle du framework (composer create-project à la version de la matrice),
 * puis commandes propres au framework (paquets requis par les choix).
 */
final class FrameworkInstallStep implements GeneratorStep
{
    public function name(): string
    {
        return 'framework';
    }

    public function label(): string
    {
        return 'Installation du framework';
    }

    public function run(GenerationContext $context): void
    {
        $spec = $context->spec;
        $version = $context->matrix->framework($spec->framework)?->version($spec->frameworkVersion)
            ?? throw new GenerationException('Version absente de la matrice.');

        if (!$context->exists('composer.json')) {
            self::assertEmptyTarget($context->targetDir);
            $parent = \dirname($context->targetDir);
            if (!is_dir($parent) && !mkdir($parent, 0o775, true) && !is_dir($parent)) {
                throw new GenerationException(\sprintf('Impossible de créer le dossier %s.', $parent));
            }

            $context->process->run([
                'composer', 'create-project', $version->package.':'.$version->constraint, $context->targetDir,
                '--no-interaction', '--prefer-dist', '--no-progress',
            ], $parent);
        }

        $profile = $context->profile();
        foreach ($profile->postInstallCommands($spec) as $command) {
            $context->process->run($command, $context->targetDir);
        }
        $profile->cleanup($context);
    }

    private static function assertEmptyTarget(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = array_diff(scandir($dir) ?: [], ['.', '..']);
        if ([] !== $entries) {
            throw new GenerationException(\sprintf("Le dossier cible %s existe déjà et n'est pas vide.", $dir));
        }
        // composer create-project exige un dossier inexistant ou vide : on retire le dossier vide.
        rmdir($dir);
    }
}
