<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Matrix\Matrix;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Process\ProcessRunner;
use Gabarit\Core\Secret\SecretGenerator;
use Gabarit\Core\Template\TemplateRenderer;
use Symfony\Component\Filesystem\Path;

/**
 * Point d'entrée unique du cœur : exécute les étapes dans l'ordre sur le dossier cible.
 */
final readonly class ProjectGenerator
{
    /**
     * @param list<GeneratorStep> $steps
     */
    public function __construct(
        private array $steps,
        private Matrix $matrix,
        private TemplateRenderer $renderer,
        private ProcessRunner $process,
        private SecretGenerator $secrets,
        private GenerationOptions $options = new GenerationOptions(),
        private ProgressListener $listener = new NullProgressListener(),
    ) {
    }

    /**
     * @throws InvalidSpecException spec refusée par la matrice
     * @throws GenerationException  échec d'une étape
     */
    public function generate(ProjectSpec $spec, string $targetDir): GenerationResult
    {
        if ('' === trim($targetDir)) {
            throw new GenerationException('Dossier cible manquant.');
        }
        // Chemin absolu : les installeurs s'exécutent depuis d'autres dossiers (cwd du parent).
        $targetDir = rtrim(Path::makeAbsolute($targetDir, (string) getcwd()), '/');

        $context = new GenerationContext($spec, $targetDir, $this->matrix, $this->renderer, $this->process, $this->secrets, $this->options);

        $done = [];
        $total = \count($this->steps);
        foreach ($this->steps as $i => $step) {
            $this->listener->stepStarted($step, $i + 1, $total);
            try {
                $step->run($context);
            } catch (InvalidSpecException|GenerationException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw GenerationException::inStep($step->name(), $e);
            }
            $this->listener->stepFinished($step, $i + 1, $total);
            $done[] = $step->name();
        }

        return new GenerationResult($spec, $targetDir, $done, $context->warnings());
    }

    public function withListener(ProgressListener $listener): self
    {
        return new self($this->steps, $this->matrix, $this->renderer, $this->process, $this->secrets, $this->options, $listener);
    }

    /**
     * @return list<GeneratorStep>
     */
    public function steps(): array
    {
        return $this->steps;
    }
}
