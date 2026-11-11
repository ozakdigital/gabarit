<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Exception\ProcessFailedException;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;

/**
 * 10. git init + premier commit (optionnel, désactivé côté web).
 */
final class GitInitStep implements GeneratorStep
{
    public function name(): string
    {
        return 'git';
    }

    public function label(): string
    {
        return 'Initialisation du dépôt git';
    }

    public function run(GenerationContext $context): void
    {
        if (!$context->options->gitInit || $context->exists('.git')) {
            return;
        }

        $dir = $context->targetDir;
        $context->process->run(['git', 'init', '--quiet', '--initial-branch=main'], $dir);
        $context->process->run(['git', 'add', '--all'], $dir);

        // Identité de l'utilisateur si configurée, sinon identité neutre pour ce seul commit.
        $identity = [];
        try {
            $context->process->run(['git', 'config', 'user.email'], $dir);
        } catch (ProcessFailedException) {
            $identity = ['-c', 'user.name=Gabarit', '-c', 'user.email=gabarit@localhost'];
        }

        $context->process->run(['git', ...$identity, 'commit', '--quiet', '-m', 'Initial commit (Gabarit)'], $dir);
    }
}
