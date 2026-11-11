<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;

/**
 * 8. Makefile, README, .gitignore, .editorconfig, CI.
 */
final class CommonFilesStep implements GeneratorStep
{
    public function name(): string
    {
        return 'common';
    }

    public function label(): string
    {
        return 'Fichiers communs (Makefile, README, CI)';
    }

    public function run(GenerationContext $context): void
    {
        $context->render('common/Makefile.twig', 'Makefile');
        $context->render('common/README.md.twig', 'README.md');
        $context->render('common/editorconfig.twig', '.editorconfig');
        $context->render('ci/github-actions.yml.twig', '.github/workflows/ci.yml');

        $this->mergeGitignore($context);
    }

    private function mergeGitignore(GenerationContext $context): void
    {
        $block = "###> gabarit ###\n".rtrim($context->renderer->render('common/gitignore.twig', $context->templateContext()))."\n###< gabarit ###";
        $current = $context->readOrEmpty('.gitignore');

        $pattern = '/^###> gabarit ###\R.*?^###< gabarit ###$/ms';
        $updated = 1 === preg_match($pattern, $current)
            ? (string) preg_replace_callback($pattern, static fn (): string => $block, $current, 1)
            : rtrim($current, "\n")."\n\n".$block."\n";

        $context->write('.gitignore', ltrim($updated, "\n"));
    }
}
