<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Support;

use Gabarit\Core\Exception\ProcessFailedException;
use Gabarit\Core\Process\ProcessRunner;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Remplace composer/npm/git dans les tests : enregistre les commandes et simule les installeurs
 * en copiant des squelettes officiels figés (tests/Fixtures/skeletons).
 */
final class FakeProcessRunner implements ProcessRunner
{
    /** @var list<array{command: list<string>, cwd: string}> */
    public array $calls = [];

    /** @var array<string, bool> */
    private array $available;

    /**
     * @param list<string> $missing exécutables à considérer comme absents
     */
    public function __construct(private readonly bool $gitIdentity = true, array $missing = [])
    {
        $this->available = array_fill_keys(['composer', 'git', 'node', 'npm', 'docker'], true);
        foreach ($missing as $tool) {
            $this->available[$tool] = false;
        }
    }

    public static function fixturesDir(): string
    {
        return \dirname(__DIR__).'/Fixtures/skeletons';
    }

    public function run(array $command, string $cwd, array $env = [], ?float $timeout = null): string
    {
        $this->calls[] = ['command' => $command, 'cwd' => $cwd];
        $fs = new Filesystem();

        if (['composer', 'create-project'] === \array_slice($command, 0, 2)) {
            $fs->mirror(self::fixturesDir().'/'.self::skeletonOf($command[2]), $command[3]);

            return '';
        }

        if ('npm' === $command[0] && \in_array('frontend', $command, true)) {
            $fs->mirror(self::fixturesDir().'/frontend/'.self::frontendOf($command), $cwd.'/frontend');

            return '';
        }

        if (['git', 'config', 'user.email'] === $command && !$this->gitIdentity) {
            throw new ProcessFailedException($command, 1, '');
        }

        if (['git', 'init'] === \array_slice($command, 0, 2)) {
            $fs->mkdir($cwd.'/.git');
        }

        return implode(' ', $command)."\n";
    }

    /**
     * Squelette figé correspondant à « paquet:contrainte » (ex. laravel/laravel:13.*).
     */
    public static function skeletonOf(string $packageConstraint): string
    {
        return match (explode(':', $packageConstraint)[0]) {
            'symfony/skeleton' => 'symfony-7.4',
            'laravel/laravel' => 'laravel-13',
            'codeigniter4/appstarter' => 'codeigniter-4.7',
            'slim/slim-skeleton' => 'slim-4',
            default => throw new \LogicException('Aucun squelette figé pour '.$packageConstraint),
        };
    }

    /**
     * Retrouve le squelette à copier depuis la commande d'installation (create-vite --template x-ts, ou Angular CLI).
     *
     * @param list<string> $command
     */
    private static function frontendOf(array $command): string
    {
        foreach ($command as $arg) {
            if (str_starts_with($arg, '@angular/cli@')) {
                return 'angular';
            }
        }
        $index = array_search('--template', $command, true);
        if (false === $index || !isset($command[$index + 1])) {
            throw new \LogicException('Commande d\'installation du front inattendue : '.implode(' ', $command));
        }

        return str_replace('-ts', '', $command[$index + 1]);
    }

    public function has(string $executable): bool
    {
        return $this->available[$executable] ?? false;
    }

    /**
     * @return list<string> commandes exécutées, sous forme de texte
     */
    public function commandLines(): array
    {
        return array_map(static fn (array $call): string => implode(' ', $call['command']), $this->calls);
    }
}
