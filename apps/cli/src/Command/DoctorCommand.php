<?php

declare(strict_types=1);

namespace Gabarit\Cli\Command;

use Gabarit\Cli\Console\ExitCode;
use Gabarit\Core\Exception\ProcessFailedException;
use Gabarit\Core\Process\ProcessRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'doctor', description: 'Vérifie les outils nécessaires (docker, composer, node, git)')]
final class DoctorCommand extends Command
{
    /** outil => [commande de version, obligatoire ?, usage] */
    private const array TOOLS = [
        'composer' => [['composer', '--version'], true, 'installation du framework'],
        'git' => [['git', '--version'], true, 'premier commit (désactivable avec --no-git)'],
        'node' => [['node', '--version'], false, 'fronts SPA (Vue, React, Svelte, Angular…) — Angular exige Node ≥ 22.22'],
        'npm' => [['npm', '--version'], false, 'fronts SPA (create-vite, Angular CLI)'],
        'docker' => [['docker', '--version'], false, 'démarrage du projet généré (make up)'],
        'docker compose' => [['docker', 'compose', 'version'], false, 'démarrage du projet généré (make up)'],
    ];

    public function __construct(private readonly ProcessRunner $process)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Sortie JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checks = [[
            'tool' => 'php',
            'ok' => \PHP_VERSION_ID >= 80300 && \extension_loaded('zip'),
            'required' => true,
            'version' => \PHP_VERSION.(\extension_loaded('zip') ? '' : ' (extension zip manquante)'),
            'usage' => 'Gabarit lui-même (PHP ≥ 8.3, ext-zip)',
        ]];

        // Lancée via la commande `gabarit` (image Docker) : Docker est sur la machine, pas dans le conteneur.
        $inContainer = '1' === getenv('GABARIT_IN_CONTAINER');

        foreach (self::TOOLS as $tool => [$command, $required, $usage]) {
            if ($inContainer && 'docker' === $command[0]) {
                $checks[] = ['tool' => $tool, 'ok' => true, 'required' => $required, 'version' => 'sur la machine hôte (utilisé par le lanceur)', 'usage' => $usage];
                continue;
            }
            $version = null;
            if ($this->process->has($command[0])) {
                try {
                    $version = trim(strtok($this->process->run($command, getcwd() ?: '.', [], 30.0), "\n") ?: '');
                } catch (ProcessFailedException) {
                    $version = null;
                }
            }
            $checks[] = ['tool' => $tool, 'ok' => null !== $version, 'required' => $required, 'version' => $version ?? 'introuvable', 'usage' => $usage];
        }

        $healthy = [] === array_filter($checks, static fn (array $c): bool => $c['required'] && !$c['ok']);

        if ($input->getOption('json')) {
            $output->writeln(json_encode(['ok' => $healthy, 'checks' => $checks], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

            return $healthy ? ExitCode::SUCCESS : ExitCode::RUNTIME_ERROR;
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('Diagnostic de l\'environnement');
        $io->table(['', 'Outil', 'Version', 'Sert à'], array_map(
            static fn (array $c): array => [$c['ok'] ? '<info>✔</info>' : ($c['required'] ? '<error>✘</error>' : '<comment>!</comment>'), $c['tool'], $c['version'], $c['usage']],
            $checks,
        ));

        if (!$healthy) {
            $io->error('Des outils obligatoires manquent.');

            return ExitCode::RUNTIME_ERROR;
        }
        $io->success('Environnement prêt.');

        return ExitCode::SUCCESS;
    }
}
