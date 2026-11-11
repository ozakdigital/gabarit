<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator;

use Gabarit\Core\Generator\Step\BrandStep;
use Gabarit\Core\Generator\Step\CommonFilesStep;
use Gabarit\Core\Generator\Step\DatabaseConfigStep;
use Gabarit\Core\Generator\Step\DockerStep;
use Gabarit\Core\Generator\Step\FrameworkInstallStep;
use Gabarit\Core\Generator\Step\FrontendInstallStep;
use Gabarit\Core\Generator\Step\GitInitStep;
use Gabarit\Core\Generator\Step\SecretsStep;
use Gabarit\Core\Generator\Step\ServicesStep;
use Gabarit\Core\Generator\Step\ValidateSpecStep;
use Gabarit\Core\Matrix\Matrix;
use Gabarit\Core\Process\ProcessRunner;
use Gabarit\Core\Secret\RandomSecretGenerator;
use Gabarit\Core\Secret\SecretGenerator;
use Gabarit\Core\Template\TemplateRenderer;

/**
 * Assemble le pipeline standard (CLAUDE.md §6). Utilisé tel quel par la CLI et par le worker web.
 */
final class ProjectGeneratorFactory
{
    /**
     * @return list<GeneratorStep>
     */
    public static function defaultSteps(): array
    {
        return [
            new ValidateSpecStep(),
            new FrameworkInstallStep(),
            new FrontendInstallStep(),
            new DatabaseConfigStep(),
            new DockerStep(),
            new ServicesStep(),
            new BrandStep(),
            new CommonFilesStep(),
            new SecretsStep(),
            new GitInitStep(),
        ];
    }

    public static function create(
        Matrix $matrix,
        ProcessRunner $process,
        GenerationOptions $options = new GenerationOptions(),
        ProgressListener $listener = new NullProgressListener(),
        ?SecretGenerator $secrets = null,
        ?TemplateRenderer $renderer = null,
    ): ProjectGenerator {
        return new ProjectGenerator(
            self::defaultSteps(),
            $matrix,
            $renderer ?? new TemplateRenderer(),
            $process,
            $secrets ?? new RandomSecretGenerator(),
            $options,
            $listener,
        );
    }
}
