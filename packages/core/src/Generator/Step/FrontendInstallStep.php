<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Frontend\SpaLayouts;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;
use Gabarit\Core\Matrix\FrontendDefinition;
use Gabarit\Core\Matrix\FrontendKind;

/**
 * 3. Installation du front choisi : Twig côté serveur, ou application SPA dans frontend/
 * créée par son installeur officiel (create-vite, Angular CLI) puis personnalisée.
 */
final class FrontendInstallStep implements GeneratorStep
{
    public const string DIR = 'frontend';

    public function name(): string
    {
        return 'frontend';
    }

    public function label(): string
    {
        return 'Installation du front';
    }

    public function run(GenerationContext $context): void
    {
        $frontend = $context->spec->frontend;
        $definition = $context->matrix->frontend($frontend)
            ?? throw new GenerationException(\sprintf('Front absent de la matrice : %s.', $frontend->value));

        if ($definition->kind->isSpa()) {
            $this->spa($context, $definition);
        } else {
            // Twig, Blade ou aucun : le profil du framework sait quoi rendre.
            $context->profile()->renderServerFrontend($context);
        }
    }

    /**
     * Commande de l'installeur officiel, toujours sous forme de tableau d'arguments.
     *
     * @return list<string>
     */
    public static function installCommand(FrontendDefinition $definition): array
    {
        $installer = $definition->installer ?? throw new GenerationException('Installeur manquant dans la matrice.');

        return match ($definition->kind) {
            FrontendKind::Vite => [
                'npm', 'exec', '--yes', '--', $installer, self::DIR,
                '--template', $definition->template ?? throw new GenerationException('Modèle create-vite manquant.'),
                '--no-interactive', '--no-immediate',
            ],
            FrontendKind::Angular => [
                'npm', 'exec', '--yes', '--', $installer, 'new', 'frontend', '--directory='.self::DIR,
                '--defaults', '--interactive=false', '--skip-git', '--skip-install',
                '--style=css', '--ssr=false', '--routing=false', '--ai-config=none',
            ],
            FrontendKind::Server => throw new GenerationException('Un front serveur n\'a pas d\'installeur.'),
        };
    }

    private function spa(GenerationContext $context, FrontendDefinition $definition): void
    {
        $layout = SpaLayouts::for($definition->frontend)
            ?? throw new GenerationException(\sprintf('Aucun profil SPA pour %s.', $definition->frontend->value));

        if (!$context->exists(self::DIR.'/package.json')) {
            $context->process->run(self::installCommand($definition), $context->targetDir);
        }

        // Décodage en objets pour préserver les objets vides.
        $package = json_decode($context->read(self::DIR.'/package.json'), false, 512, \JSON_THROW_ON_ERROR);
        if (!$package instanceof \stdClass) {
            throw new GenerationException('frontend/package.json invalide.');
        }
        $package->name = $context->spec->name.'-frontend';
        $context->write(self::DIR.'/package.json', json_encode($package, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");

        foreach ($layout->renders as $template => $target) {
            $context->render($template, self::DIR.'/'.$target);
        }

        // Fichiers de démonstration que nos composants n'utilisent plus.
        foreach ($layout->remove as $file) {
            if ($context->exists(self::DIR.'/'.$file)) {
                unlink($context->path(self::DIR.'/'.$file));
            }
        }
    }
}
