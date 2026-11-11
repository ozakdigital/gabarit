<?php

declare(strict_types=1);

namespace Gabarit\Cli\Command;

use Gabarit\Cli\Console\ConsoleProgressListener;
use Gabarit\Cli\Console\ExitCode;
use Gabarit\Cli\Interview\ConsoleInterviewer;
use Gabarit\Cli\Interview\Interviewer;
use Gabarit\Cli\Interview\PromptsInterviewer;
use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Generator\GenerationOptions;
use Gabarit\Core\Generator\NullProgressListener;
use Gabarit\Core\Generator\ProjectGeneratorFactory;
use Gabarit\Core\Matrix\Matrix;
use Gabarit\Core\Matrix\SpecValidator;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Process\ProcessRunner;
use Gabarit\Core\Secret\SecretGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * La commande ne fait que collecter les choix et appeler le cœur : aucune règle métier ici.
 */
#[AsCommand(name: 'new', description: 'Génère un nouveau projet')]
final class NewCommand extends Command
{
    /**
     * @param (\Closure(SymfonyStyle): Interviewer)|null $interviewerFactory
     */
    public function __construct(
        private readonly Matrix $matrix,
        private readonly ProcessRunner $process,
        private readonly ?SecretGenerator $secrets = null,
        private readonly ?\Closure $interviewerFactory = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Nom du projet (slug [a-z0-9-])')
            ->addOption('framework', null, InputOption::VALUE_REQUIRED, 'Framework PHP')
            ->addOption('fw-version', null, InputOption::VALUE_REQUIRED, 'Version du framework')
            ->addOption('php', null, InputOption::VALUE_REQUIRED, 'Version de PHP')
            ->addOption('db', null, InputOption::VALUE_REQUIRED, 'Base de données')
            ->addOption('frontend', null, InputOption::VALUE_REQUIRED, 'Front')
            ->addOption('services', null, InputOption::VALUE_REQUIRED, 'Services annexes, séparés par des virgules (ou « none »)')
            ->addOption('brand-name', null, InputOption::VALUE_REQUIRED, "Nom d'affichage de la marque")
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Namespace PHP (ex. Acme\\Shop)')
            ->addOption('primary-color', null, InputOption::VALUE_REQUIRED, 'Couleur principale #rrggbb')
            ->addOption('secondary-color', null, InputOption::VALUE_REQUIRED, 'Couleur secondaire #rrggbb')
            ->addOption('logo', null, InputOption::VALUE_REQUIRED, 'Chemin vers un logo PNG ou SVG')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Dossier cible (défaut : ./<nom>)')
            ->addOption('no-git', null, InputOption::VALUE_NONE, 'Ne pas initialiser de dépôt git')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Sortie JSON (implique --no-interaction)')
            ->setHelp(<<<'HELP'
                Mode interactif par défaut : les choix manquants sont demandés via des menus.

                  <info>gabarit new mon-app</info>
                  <info>gabarit new mon-app --framework=symfony --fw-version=7.4 --php=8.3 --db=postgresql --frontend=vue --services=redis --no-interaction</info>

                Codes de sortie : 0 succès, 1 erreur de validation, 2 erreur d'exécution.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = (bool) $input->getOption('json');
        if ($json) {
            $input->setInteractive(false);
        }
        $io = new SymfonyStyle($input, $output);

        try {
            $data = $this->collect($input, $io);
            $spec = ProjectSpec::fromArray($data);
            (new SpecValidator($this->matrix))->assertValid($spec);
        } catch (InvalidSpecException $e) {
            return $this->fail($io, $json, ExitCode::VALIDATION_ERROR, 'Choix invalides.', $e->violations);
        }

        $dir = $this->option($input, 'dir') ?? (getcwd() ?: '.').\DIRECTORY_SEPARATOR.$spec->name;

        if (!$json) {
            $io->title(\sprintf('Gabarit — %s', $spec->brand->displayName));
            $io->definitionList(
                ['Framework' => \sprintf('%s %s', $spec->framework->name, $spec->frameworkVersion)],
                ['PHP' => $spec->phpVersion],
                ['Base' => $spec->database->value],
                ['Front' => $spec->frontend->value],
                ['Services' => [] === $spec->services ? 'aucun' : implode(', ', array_map(static fn ($s): string => $s->value, $spec->services))],
                ['Dossier' => $dir],
            );
        }

        $generator = ProjectGeneratorFactory::create(
            $this->matrix,
            $this->process,
            new GenerationOptions(gitInit: !$input->getOption('no-git')),
            $json ? new NullProgressListener() : new ConsoleProgressListener($output),
            $this->secrets,
        );

        try {
            $result = $generator->generate($spec, $dir);
        } catch (InvalidSpecException $e) {
            return $this->fail($io, $json, ExitCode::VALIDATION_ERROR, 'Choix invalides.', $e->violations);
        } catch (GenerationException $e) {
            return $this->fail($io, $json, ExitCode::RUNTIME_ERROR, $e->getMessage());
        }

        if ($json) {
            $output->writeln(json_encode(['status' => 'ok'] + $result->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

            return ExitCode::SUCCESS;
        }

        foreach ($result->warnings as $warning) {
            $io->warning($warning);
        }
        $io->success(\sprintf('Projet « %s » généré dans %s', $spec->name, $result->targetDir));
        $io->text(['Pour démarrer :', \sprintf('  cd %s', $result->targetDir), '  make up']);

        return ExitCode::SUCCESS;
    }

    /**
     * Rassemble les choix depuis les options, et demande les manquants en mode interactif.
     * Les menus sont construits depuis la matrice ; la validation finale reste celle du cœur.
     *
     * @return array<string, mixed>
     */
    private function collect(InputInterface $input, SymfonyStyle $io): array
    {
        $ask = $input->isInteractive() ? $this->interviewer($io) : null;

        $name = $this->argument($input);
        if (null === $name && null !== $ask) {
            $name = $ask->text('Nom du projet', 'mon-app', static fn (string $v): ?string => 1 === preg_match('/^[a-z0-9][a-z0-9-]{0,38}[a-z0-9]$/', $v) ? null : '2 à 40 caractères parmi [a-z0-9-].');
        }

        $framework = $this->option($input, 'framework');
        if (null === $framework) {
            $options = array_map(static fn ($f): string => $f->label, $this->matrix->frameworks);
            $framework = null !== $ask && \count($options) > 1 ? $ask->select('Framework', $options) : (string) array_key_first($options);
        }
        $definition = $this->matrix->framework(Framework::tryFrom($framework) ?? Framework::Symfony);

        $version = $this->option($input, 'fw-version');
        if (null === $version && null !== $definition) {
            $versions = array_keys($definition->versions);
            $options = array_combine($versions, array_map(static fn (string $v): string => $definition->label.' '.$v, $versions));
            $version = null !== $ask && \count($options) > 1 ? $ask->select('Version', $options) : (string) end($versions);
        }

        $php = $this->option($input, 'php');
        $phpVersions = $definition?->version((string) $version)->phpVersions ?? [];
        if (null === $php && [] !== $phpVersions) {
            $default = \in_array('8.3', $phpVersions, true) ? '8.3' : (string) end($phpVersions);
            $php = null !== $ask
                ? $ask->select('Version de PHP', array_combine($phpVersions, array_map(static fn (string $v): string => 'PHP '.$v, $phpVersions)), $default)
                : $default;
        }

        $db = $this->option($input, 'db');
        if (null === $db && null !== $definition) {
            $options = [];
            foreach ($definition->databases as $database) {
                $options[$database->value] = $this->matrix->database($database)->label ?? $database->value;
            }
            $db = null !== $ask ? $ask->select('Base de données', $options) : (string) array_key_first($options);
        }

        $frontend = $this->option($input, 'frontend');
        if (null === $frontend && null !== $definition) {
            $options = [];
            foreach ($definition->frontends as $front) {
                $options[$front->value] = $this->matrix->frontend($front)->label ?? $front->value;
            }
            $frontend = null !== $ask ? $ask->select('Front', $options) : (string) array_key_first($options);
        }

        $servicesOption = $this->option($input, 'services');
        if (null !== $servicesOption) {
            $services = 'none' === $servicesOption ? [] : array_values(array_filter(array_map('trim', explode(',', $servicesOption))));
        } elseif (null !== $ask && [] !== $this->matrix->services) {
            $services = $ask->multiselect('Services annexes', array_map(static fn ($s): string => $s->label, $this->matrix->services));
        } else {
            $services = [];
        }

        $default = Brand::fromProjectName($name ?? 'app');
        $brandName = $this->option($input, 'brand-name');
        $namespace = $this->option($input, 'namespace');
        if (null !== $ask) {
            $brandName ??= $ask->text("Nom d'affichage", $default->displayName, static fn (string $v): ?string => '' === $v ? 'Obligatoire.' : null);
            // Question posée seulement si la matrice autorise un namespace de marque pour ce framework.
            if (null === $namespace && ($definition->customNamespace ?? false)) {
                $namespace = $ask->text('Namespace PHP', $default->phpNamespace, static fn (string $v): ?string => 1 === preg_match('/^[A-Z][A-Za-z0-9]*(\\\\[A-Z][A-Za-z0-9]*)*$/', $v) ? null : 'Format attendu : Acme ou Acme\\Shop.');
            }
        }

        $brand = array_filter([
            'displayName' => $brandName,
            'phpNamespace' => $namespace,
            'primaryColor' => $this->option($input, 'primary-color'),
            'secondaryColor' => $this->option($input, 'secondary-color'),
            'logoPath' => $this->option($input, 'logo'),
        ], static fn (?string $v): bool => null !== $v);

        return [
            'name' => $name,
            'framework' => $framework,
            'frameworkVersion' => $version,
            'phpVersion' => $php,
            'database' => $db,
            'frontend' => $frontend,
            'services' => $services,
            'brand' => [] === $brand ? null : $brand,
        ];
    }

    private function interviewer(SymfonyStyle $io): Interviewer
    {
        if (null !== $this->interviewerFactory) {
            return ($this->interviewerFactory)($io);
        }

        // Laravel Prompts ne gère pas le terminal Windows natif : repli sur les questions Symfony.
        return \PHP_OS_FAMILY === 'Windows' ? new ConsoleInterviewer($io) : new PromptsInterviewer();
    }

    private function argument(InputInterface $input): ?string
    {
        $value = $input->getArgument('name');

        return \is_string($value) && '' !== $value ? $value : null;
    }

    private function option(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @param array<string, string> $violations
     */
    private function fail(SymfonyStyle $io, bool $json, int $code, string $message, array $violations = []): int
    {
        if ($json) {
            $io->writeln((string) json_encode(['status' => 'error', 'message' => $message, 'violations' => (object) $violations], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return $code;
        }

        $lines = [];
        foreach ($violations as $field => $text) {
            $lines[] = \sprintf('%s : %s', $field, $text);
        }
        $io->error([$message, ...$lines]);

        return $code;
    }
}
