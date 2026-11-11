<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Generator;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;
use Gabarit\Core\Generator\ProgressListener;
use Gabarit\Core\Generator\ProjectGenerator;
use Gabarit\Core\Generator\ProjectGeneratorFactory;
use Gabarit\Core\Template\TemplateRenderer;
use Gabarit\Core\Tests\Support\FakeProcessRunner;
use Gabarit\Core\Tests\Support\FixedSecretGenerator;
use Gabarit\Core\Tests\Support\TestCase;
use Symfony\Component\Filesystem\Path;

final class ProjectGeneratorTest extends TestCase
{
    public function testDefaultPipelineOrderMatchesClaudeMd(): void
    {
        $names = array_map(static fn (GeneratorStep $s): string => $s->name(), ProjectGeneratorFactory::defaultSteps());

        self::assertSame(['validate', 'framework', 'frontend', 'database', 'docker', 'services', 'brand', 'common', 'secrets', 'git'], $names);
    }

    public function testStepsRunInOrderAndProgressIsReported(): void
    {
        /** @var \ArrayObject<int, string> $log */
        $log = new \ArrayObject();
        $steps = [self::step('a', $log), self::step('b', $log)];
        $listener = new class($log) implements ProgressListener {
            /** @param \ArrayObject<int, string> $log */
            public function __construct(private \ArrayObject $log)
            {
            }

            public function stepStarted(GeneratorStep $step, int $index, int $total): void
            {
                $this->log[] = "start {$step->name()} $index/$total";
            }

            public function stepFinished(GeneratorStep $step, int $index, int $total): void
            {
                $this->log[] = "end {$step->name()}";
            }
        };

        $result = $this->generator($steps)->withListener($listener)->generate(self::spec(), $this->tempDir().'/x');

        self::assertSame(['a', 'b'], $result->steps);
        self::assertSame(['start a 1/2', 'run a', 'end a', 'start b 2/2', 'run b', 'end b'], $log->getArrayCopy());
    }

    public function testRelativeTargetIsResolvedAgainstTheWorkingDirectory(): void
    {
        $base = $this->tempDir();
        $previous = (string) getcwd();
        chdir($base);
        try {
            $result = $this->generator([])->generate(self::spec(), 'sous/dossier');
        } finally {
            chdir($previous);
        }

        self::assertSame(Path::canonicalize((string) realpath($base)).'/sous/dossier', $result->targetDir);
    }

    public function testUnexpectedErrorsAreWrappedWithTheStepName(): void
    {
        $failing = new class implements GeneratorStep {
            public function name(): string
            {
                return 'boom';
            }

            public function label(): string
            {
                return 'Boom';
            }

            public function run(GenerationContext $context): void
            {
                throw new \RuntimeException('disque plein');
            }
        };

        try {
            $this->generator([$failing])->generate(self::spec(), $this->tempDir().'/x');
            self::fail('Exception attendue');
        } catch (GenerationException $e) {
            self::assertSame('boom', $e->step);
            self::assertStringContainsString('disque plein', $e->getMessage());
        }
    }

    public function testInvalidSpecStopsBeforeAnyInstall(): void
    {
        $process = new FakeProcessRunner();
        $generator = ProjectGeneratorFactory::create(self::matrix(), $process, secrets: new FixedSecretGenerator());

        try {
            $generator->generate(self::spec(php: '7.4'), $this->tempDir().'/x');
            self::fail('Exception attendue');
        } catch (InvalidSpecException $e) {
            self::assertArrayHasKey('phpVersion', $e->violations);
        }
        self::assertSame([], $process->calls);
    }

    /**
     * @param \ArrayObject<int, string> $log
     */
    private static function step(string $name, \ArrayObject $log): GeneratorStep
    {
        return new class($name, $log) implements GeneratorStep {
            /** @param \ArrayObject<int, string> $log */
            public function __construct(private string $stepName, private \ArrayObject $log)
            {
            }

            public function name(): string
            {
                return $this->stepName;
            }

            public function label(): string
            {
                return $this->stepName;
            }

            public function run(GenerationContext $context): void
            {
                $this->log[] = 'run '.$this->stepName;
            }
        };
    }

    /**
     * @param list<GeneratorStep> $steps
     */
    private function generator(array $steps): ProjectGenerator
    {
        return new ProjectGenerator($steps, self::matrix(), new TemplateRenderer(), new FakeProcessRunner(), new FixedSecretGenerator());
    }
}
