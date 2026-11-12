<?php

declare(strict_types=1);

namespace Gabarit\Cli\Command;

use Gabarit\Cli\Console\ConsoleProgressListener;
use Gabarit\Cli\Console\ExitCode;
use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Generator\GenerationOptions;
use Gabarit\Core\Generator\ProjectGeneratorFactory;
use Gabarit\Core\Matrix\Matrix;
use Gabarit\Core\Process\ProcessRunner;
use Gabarit\Core\Secret\SecretGenerator;
use Gabarit\Core\Starter\StarterApplier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Installe un starter téléchargé depuis le site (gabarit.json) : appelé par `bin/up` au premier lancement.
 */
#[AsCommand(name: 'apply', description: 'Transforme un starter décompressé (gabarit.json) en projet complet')]
final class ApplyCommand extends Command
{
    public function __construct(
        private readonly Matrix $matrix,
        private readonly ProcessRunner $process,
        private readonly ?SecretGenerator $secrets = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dir', InputArgument::OPTIONAL, 'Dossier du starter', '.')
            ->addOption('no-git', null, InputOption::VALUE_NONE, 'Ne pas initialiser de dépôt git');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = (string) $input->getArgument('dir');

        $generator = ProjectGeneratorFactory::create(
            $this->matrix,
            $this->process,
            new GenerationOptions(gitInit: false),
            new ConsoleProgressListener($output),
            $this->secrets,
        );
        $applier = new StarterApplier($generator, $this->process, !$input->getOption('no-git'));

        try {
            $spec = StarterApplier::readSpec($dir);
            $io->title(\sprintf('Gabarit — installation de « %s »', $spec->brand->displayName));
            $result = $applier->apply($dir);
        } catch (InvalidSpecException $e) {
            $io->error(['gabarit.json contient des choix invalides.', ...array_map(
                static fn (string $field, string $message): string => $field.' : '.$message,
                array_keys($e->violations),
                $e->violations,
            )]);

            return ExitCode::VALIDATION_ERROR;
        } catch (GenerationException $e) {
            $io->error($e->getMessage());

            return ExitCode::RUNTIME_ERROR;
        }

        foreach ($result->warnings as $warning) {
            $io->warning($warning);
        }
        $io->success(\sprintf('Projet « %s » installé.', $result->spec->name));

        return ExitCode::SUCCESS;
    }
}
