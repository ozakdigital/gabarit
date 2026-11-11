<?php

declare(strict_types=1);

namespace Gabarit\Cli\Command;

use Gabarit\Cli\Console\ExitCode;
use Gabarit\Core\Matrix\Matrix;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Frontend;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'matrix', description: 'Affiche les combinaisons disponibles')]
final class MatrixCommand extends Command
{
    public function __construct(private readonly Matrix $matrix)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Sortie JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('json')) {
            $output->writeln(json_encode($this->matrix->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

            return ExitCode::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('Matrice de compatibilité');

        $rows = [];
        foreach ($this->matrix->frameworks as $definition) {
            foreach ($definition->versions as $version) {
                $rows[] = [
                    $definition->label.' '.$version->version,
                    implode(', ', $version->phpVersions),
                    implode(', ', array_map(static fn (Database $d): string => $d->value, $definition->databases)),
                    implode(', ', array_map(static fn (Frontend $f): string => $f->value, $definition->frontends)),
                ];
            }
        }
        $io->table(['Framework', 'PHP', 'Bases', 'Fronts'], $rows);

        $services = [];
        foreach ($this->matrix->services as $key => $service) {
            $services[] = [$key, $service->label, $service->image];
        }
        $io->table(['Service', 'Description', 'Image'], $services);
        $io->text(\sprintf('%d combinaisons (hors services).', \count($this->matrix->combinations())));

        return ExitCode::SUCCESS;
    }
}
