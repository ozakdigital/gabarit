<?php

declare(strict_types=1);

namespace Gabarit\Cli\Tests;

use Gabarit\Cli\Application;
use Gabarit\Cli\Command\NewCommand;
use Gabarit\Cli\Console\ExitCode;
use Gabarit\Cli\Interview\Interviewer;
use Gabarit\Core\Tests\Support\FakeProcessRunner;
use Gabarit\Core\Tests\Support\FixedSecretGenerator;
use Gabarit\Core\Tests\Support\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandsTest extends TestCase
{
    public function testMatrixJsonIsThePublicMatrix(): void
    {
        $tester = new CommandTester((new Application(self::matrix(), new FakeProcessRunner()))->find('matrix'));

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--json' => true]));
        self::assertSame(self::matrix()->toArray(), json_decode($tester->getDisplay(), true));
    }

    public function testMatrixTable(): void
    {
        $tester = new CommandTester((new Application(self::matrix(), new FakeProcessRunner()))->find('matrix'));

        $tester->execute([]);

        self::assertStringContainsString('Symfony 7.4', $tester->getDisplay());
        self::assertStringContainsString('328 combinaisons', $tester->getDisplay());
        self::assertStringContainsString('Laravel 13', $tester->getDisplay());
        self::assertStringContainsString('angular', $tester->getDisplay());
    }

    public function testNewNonInteractiveGeneratesTheProject(): void
    {
        $dir = $this->tempDir().'/mon-app';
        $process = new FakeProcessRunner();
        $tester = new CommandTester(new NewCommand(self::matrix(), $process, new FixedSecretGenerator()));

        $code = $tester->execute([
            'name' => 'mon-app', '--framework' => 'symfony', '--fw-version' => '7.4', '--php' => '8.3',
            '--db' => 'postgresql', '--frontend' => 'vue', '--services' => 'redis,mailpit',
            '--namespace' => 'Acme', '--dir' => $dir, '--no-git' => true,
        ], ['interactive' => false]);

        self::assertSame(ExitCode::SUCCESS, $code, $tester->getDisplay());
        self::assertStringContainsString('make up', $tester->getDisplay());
        self::assertFileExists($dir.'/compose.yaml');
        self::assertStringContainsString('"acme/mon-app"', (string) file_get_contents($dir.'/composer.json'));
    }

    public function testNewUsesMatrixDefaultsWithoutInteraction(): void
    {
        $dir = $this->tempDir().'/defaut';
        $tester = new CommandTester(new NewCommand(self::matrix(), new FakeProcessRunner(), new FixedSecretGenerator()));

        $tester->execute(['name' => 'defaut', '--dir' => $dir, '--no-git' => true, '--json' => true]);

        self::assertSame(ExitCode::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $json = json_decode($tester->getDisplay(), true);
        self::assertSame('ok', $json['status']);
        self::assertSame(['name' => 'defaut', 'framework' => 'symfony', 'frameworkVersion' => '7.4', 'phpVersion' => '8.3', 'database' => 'postgresql', 'frontend' => 'twig', 'services' => []], array_diff_key($json['spec'], ['brand' => 1]));
    }

    public function testValidationErrorExitsWithOne(): void
    {
        $tester = new CommandTester(new NewCommand(self::matrix(), new FakeProcessRunner()));

        $code = $tester->execute(['name' => 'mon-app', '--php' => '7.4', '--json' => true]);

        self::assertSame(ExitCode::VALIDATION_ERROR, $code);
        $json = json_decode($tester->getDisplay(), true);
        self::assertSame('error', $json['status']);
        self::assertArrayHasKey('phpVersion', $json['violations']);
    }

    public function testRuntimeErrorExitsWithTwo(): void
    {
        $dir = $this->tempDir().'/occupe';
        mkdir($dir);
        file_put_contents($dir.'/fichier.txt', 'x');
        $tester = new CommandTester(new NewCommand(self::matrix(), new FakeProcessRunner()));

        $code = $tester->execute(['name' => 'occupe', '--dir' => $dir], ['interactive' => false]);

        self::assertSame(ExitCode::RUNTIME_ERROR, $code);
        self::assertStringContainsString('pas vide', $tester->getDisplay());
    }

    public function testInteractiveModeAsksForMissingChoicesFromTheMatrix(): void
    {
        /** @var \ArrayObject<int, string> $asked */
        $asked = new \ArrayObject();
        $interviewer = new class($asked) implements Interviewer {
            /** @param \ArrayObject<int, string> $asked */
            public function __construct(private \ArrayObject $asked)
            {
            }

            public function select(string $label, array $options, ?string $default = null): string
            {
                $this->asked[] = $label.' ['.implode('|', array_keys($options)).']';

                return match ($label) {
                    'Framework' => 'laravel',
                    'Base de données' => 'mariadb',
                    default => $default ?? (string) array_key_first($options),
                };
            }

            public function multiselect(string $label, array $options): array
            {
                $this->asked[] = $label.' ['.implode('|', array_keys($options)).']';

                return ['mailpit'];
            }

            public function text(string $label, string $default, \Closure $validate): string
            {
                $this->asked[] = $label;

                return $default;
            }
        };

        $dir = $this->tempDir().'/inter';
        $tester = new CommandTester(new NewCommand(self::matrix(), new FakeProcessRunner(), new FixedSecretGenerator(), static fn () => $interviewer));
        $code = $tester->execute(['name' => 'inter', '--dir' => $dir, '--no-git' => true], ['interactive' => true]);

        self::assertSame(ExitCode::SUCCESS, $code, $tester->getDisplay());
        // Une seule version de Laravel dans la matrice : elle n'est pas demandée.
        self::assertSame([
            'Framework [symfony|laravel|codeigniter|slim]',
            'Version de PHP [8.3|8.4]',
            'Base de données [postgresql|mysql|mariadb|sqlite]',
            'Front [blade|none|vue|react|svelte|angular|solid|preact]',
            'Services annexes [redis|mailpit]',
            "Nom d'affichage",
            'Namespace PHP',
        ], $asked->getArrayCopy());
        self::assertStringContainsString('mariadb:11.8', (string) file_get_contents($dir.'/compose.yaml'));
        self::assertStringContainsString('mailpit:', (string) file_get_contents($dir.'/compose.yaml'));
        self::assertStringContainsString('DB_CONNECTION="mariadb"', (string) file_get_contents($dir.'/.env'));
    }

    public function testCodeIgniterDoesNotAskForANamespace(): void
    {
        /** @var \ArrayObject<int, string> $asked */
        $asked = new \ArrayObject();
        $interviewer = new class($asked) implements Interviewer {
            /** @param \ArrayObject<int, string> $asked */
            public function __construct(private \ArrayObject $asked)
            {
            }

            public function select(string $label, array $options, ?string $default = null): string
            {
                return 'Framework' === $label ? 'codeigniter' : ($default ?? (string) array_key_first($options));
            }

            public function multiselect(string $label, array $options): array
            {
                return [];
            }

            public function text(string $label, string $default, \Closure $validate): string
            {
                $this->asked[] = $label;

                return $default;
            }
        };

        $dir = $this->tempDir().'/ci-app';
        $tester = new CommandTester(new NewCommand(self::matrix(), new FakeProcessRunner(), new FixedSecretGenerator(), static fn () => $interviewer));

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['name' => 'ci-app', '--dir' => $dir, '--no-git' => true], ['interactive' => true]), $tester->getDisplay());
        self::assertSame(["Nom d'affichage"], $asked->getArrayCopy());
        self::assertFileExists($dir.'/app/Controllers/Health.php');
    }

    public function testDoctorReportsMissingRequiredTools(): void
    {
        $ok = new CommandTester((new Application(self::matrix(), new FakeProcessRunner()))->find('doctor'));
        self::assertSame(ExitCode::SUCCESS, $ok->execute(['--json' => true]));

        $ko = new CommandTester((new Application(self::matrix(), new FakeProcessRunner(missing: ['composer'])))->find('doctor'));
        self::assertSame(ExitCode::RUNTIME_ERROR, $ko->execute(['--json' => true]));
        $json = json_decode($ko->getDisplay(), true);
        self::assertFalse($json['ok']);
    }
}
