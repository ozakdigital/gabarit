<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Generation;

use Gabarit\Core\Framework\FrameworkProfiles;
use Gabarit\Core\Frontend\SpaLayouts;
use Gabarit\Core\Generator\GenerationOptions;
use Gabarit\Core\Generator\ProjectGeneratorFactory;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Process\SymfonyProcessRunner;
use Gabarit\Core\Support\EnvFile;
use Gabarit\Core\Tests\Support\FakeProcessRunner;
use Gabarit\Core\Tests\Support\FixedSecretGenerator;
use Gabarit\Core\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Génération complète de CHAQUE combinaison de la matrice (CLAUDE.md §11).
 * Les installeurs sont simulés par des squelettes officiels figés ; `docker compose config`
 * valide la syntaxe du compose produit quand le client Docker est disponible.
 */
#[Group('generation')]
final class GenerationTest extends TestCase
{
    /** Fichier du test de santé généré, par framework. */
    private const array HEALTH_FILES = [
        'symfony' => 'src/Controller/HealthController.php',
        'laravel' => 'app/Http/Controllers/HealthController.php',
        'codeigniter' => 'app/Controllers/Health.php',
        'slim' => 'app/health.php',
    ];

    /**
     * Toutes les combinaisons de la matrice si GABARIT_FULL_MATRIX=1 (nightly), sinon un échantillon
     * (CLAUDE.md §11) qui couvre chaque paire framework × front, framework × base et framework × PHP.
     *
     * @return iterable<string, array{ProjectSpec}>
     */
    public static function combinations(): iterable
    {
        $full = '1' === getenv('GABARIT_FULL_MATRIX');
        $serviceSets = [[], [Service::Redis, Service::Mailpit]];

        foreach (self::matrix()->frameworks as $definition) {
            $namespace = $definition->customNamespace ? 'Demo\\App' : 'App';
            foreach ($definition->versions as $version) {
                $i = 0;
                foreach ($definition->frontends as $frontend) {
                    foreach ($definition->databases as $d => $database) {
                        foreach ($version->phpVersions as $p => $php) {
                            foreach ($serviceSets as $s => $services) {
                                // Échantillon : une base, une version de PHP et un jeu de services par front, en rotation.
                                $sampled = $d === $i % \count($definition->databases)
                                    && $p === $i % \count($version->phpVersions)
                                    && $s === $i % 2;
                                if (!$full && !$sampled) {
                                    continue;
                                }
                                $label = \sprintf('%s %s / PHP %s / %s / %s / %s', $definition->framework->value, $version->version, $php, $database->value, $frontend->value, [] === $services ? 'sans service' : 'tous services');
                                yield $label => [new ProjectSpec('demo-app', $definition->framework, $version->version, $php, $database, $frontend, $services, new Brand('Démo', $namespace, '#123456'))];
                            }
                        }
                    }
                    ++$i;
                }
            }
        }
    }

    public function testSampleCoversEveryFrameworkFrontendAndDatabase(): void
    {
        $pairs = [];
        foreach (self::combinations() as [$spec]) {
            $pairs['fe:'.$spec->framework->value.'/'.$spec->frontend->value] = true;
            $pairs['db:'.$spec->framework->value.'/'.$spec->database->value] = true;
            $pairs['php:'.$spec->framework->value.'/'.$spec->phpVersion] = true;
        }
        foreach (self::matrix()->combinations() as $c) {
            self::assertArrayHasKey('fe:'.$c['framework']->value.'/'.$c['frontend']->value, $pairs);
            self::assertArrayHasKey('db:'.$c['framework']->value.'/'.$c['database']->value, $pairs);
            self::assertArrayHasKey('php:'.$c['framework']->value.'/'.$c['phpVersion'], $pairs);
        }
    }

    #[DataProvider('combinations')]
    public function testCombinationGeneratesAStartableProject(ProjectSpec $spec): void
    {
        $dir = $this->tempDir().'/'.$spec->name;
        $generator = ProjectGeneratorFactory::create(self::matrix(), new FakeProcessRunner(), new GenerationOptions(gitInit: false), secrets: new FixedSecretGenerator());

        $result = $generator->generate($spec, $dir);

        $profile = FrameworkProfiles::for($spec->framework);
        $healthFile = self::HEALTH_FILES[$spec->framework->value];

        self::assertCount(10, $result->steps);
        foreach (['Dockerfile', 'compose.yaml', 'docker/nginx/default.conf', 'docker/php/app.ini', 'docker/php/fpm.conf', 'docker/php/entrypoint.sh', '.gitattributes', '.dockerignore', 'Makefile', 'README.md', $profile->secretsFile(), '.gitignore', '.github/workflows/ci.yml', $healthFile] as $file) {
            self::assertFileExists($dir.'/'.$file);
        }
        self::assertStringContainsString('/'.$profile->secretsFile(), (string) file_get_contents($dir.'/.gitignore'), 'Le fichier de secrets doit être ignoré par git');

        $compose = Yaml::parseFile($dir.'/compose.yaml');
        self::assertIsArray($compose);
        $dbServer = self::matrix()->database($spec->database)->server ?? false;
        $expected = $dbServer ? ['app', 'nginx', 'database'] : ['app', 'nginx'];
        $spa = SpaLayouts::for($spec->frontend);
        if (null !== $spa) {
            $expected[] = 'node';
            // Les fichiers personnalisés existent, le thème est importé et le titre porte la marque.
            foreach ($spa->renders as $target) {
                self::assertFileExists($dir.'/frontend/'.$target);
            }
            foreach ($spa->remove as $removed) {
                self::assertFileDoesNotExist($dir.'/frontend/'.$removed);
            }
            self::assertStringContainsString($spa->styleImport, (string) file_get_contents($dir.'/frontend/'.$spa->styleFile));
            self::assertStringContainsString('<title>Démo</title>', (string) file_get_contents($dir.'/frontend/'.$spa->indexHtml));
            self::assertFileExists($dir.'/frontend/src/brand.css');
            self::assertSame('demo-app-frontend', json_decode((string) file_get_contents($dir.'/frontend/package.json'), true)['name']);
        }
        foreach ($spec->services as $service) {
            $expected[] = $service->value;
        }
        self::assertSame($expected, array_keys($compose['services']));
        if ($dbServer) {
            self::assertSame(self::matrix()->database($spec->database)?->image, $compose['services']['database']['image']);
        }

        $dockerfile = (string) file_get_contents($dir.'/Dockerfile');
        self::assertStringContainsString('FROM php:'.$spec->phpVersion.'-fpm-alpine', $dockerfile);
        foreach ($profile->phpExtensions($spec->database) as $extension) {
            self::assertStringContainsString(' '.$extension, $dockerfile);
        }
        self::assertStringContainsString('ENTRYPOINT ["app-entrypoint"]', $dockerfile);
        $entrypoint = (string) file_get_contents($dir.'/docker/php/entrypoint.sh');
        foreach ($profile->writablePaths() as $path) {
            self::assertStringContainsString(' '.$path, $entrypoint, 'Dossier inscriptible manquant');
        }

        $health = (string) file_get_contents($dir.'/'.$healthFile);
        self::assertStringContainsString('"Démo"', $health);
        if (Framework::Slim !== $spec->framework) {
            self::assertStringContainsString('namespace '.$spec->brand->phpNamespace.'\\', $health);
        }
        if (Framework::Laravel === $spec->framework || Framework::Symfony === $spec->framework) {
            $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true);
            self::assertIsArray($composer);
            foreach (['autoload', 'autoload-dev'] as $section) {
                foreach (array_keys($composer[$section]['psr-4'] ?? []) as $prefix) {
                    self::assertStringStartsNotWith('App\\', (string) $prefix, "Namespace App\\ non remplacé dans $section");
                }
            }
            self::assertArrayHasKey('Demo\\App\\', $composer['autoload']['psr-4']);
        }

        if ($dbServer) {
            $secrets = EnvFile::parse((string) file_get_contents($dir.'/'.$profile->secretsFile()));
            self::assertNotEmpty($secrets['DB_PASSWORD']);
        }

        $this->assertDockerComposeConfigIsValid($dir, $profile->secretsFile());
        $this->assertComposerJsonIsValid($dir);
    }

    public function testGenerationIsDeterministic(): void
    {
        $spec = self::spec(frontend: Frontend::Vue, services: [Service::Redis]);
        $outputs = [];
        foreach ([1, 2] as $run) {
            $dir = $this->tempDir().'/'.$spec->name;
            ProjectGeneratorFactory::create(self::matrix(), new FakeProcessRunner(), new GenerationOptions(gitInit: false), secrets: new FixedSecretGenerator())->generate($spec, $dir);
            $outputs[$run] = $this->tree($dir);
        }

        self::assertSame($outputs[1], $outputs[2]);
    }

    /**
     * Génération réelle (composer, npm) : lente et dépendante du réseau, réservée au nightly.
     */
    #[Group('real-installers')]
    public function testRealInstallers(): void
    {
        $dir = $this->tempDir().'/real-app';
        $spec = self::spec(name: 'real-app', frontend: Frontend::Vue, services: [Service::Redis, Service::Mailpit], brand: new Brand('Réel', 'Real\\App'));

        $result = ProjectGeneratorFactory::create(self::matrix(), new SymfonyProcessRunner(), new GenerationOptions(gitInit: true))->generate($spec, $dir);

        self::assertCount(10, $result->steps);
        self::assertDirectoryExists($dir.'/vendor');
        self::assertDirectoryExists($dir.'/.git');
        $this->assertDockerComposeConfigIsValid($dir, '.env.local');

        $lint = new Process(['php', 'bin/console', 'lint:container'], $dir);
        $lint->run();
        self::assertTrue($lint->isSuccessful(), $lint->getErrorOutput().$lint->getOutput());
    }

    private function assertDockerComposeConfigIsValid(string $dir, string $envFile): void
    {
        if (null === (new ExecutableFinder())->find('docker')) {
            return; // syntaxe déjà vérifiée par le parseur YAML ; la CI dispose de Docker
        }

        $process = new Process(['docker', 'compose', '--env-file', $envFile, 'config', '--quiet'], $dir);
        $process->run();
        self::assertTrue($process->isSuccessful(), "docker compose config a échoué :\n".$process->getErrorOutput());
    }

    private function assertComposerJsonIsValid(string $dir): void
    {
        if (null === (new ExecutableFinder())->find('composer')) {
            return;
        }

        // Schéma uniquement : pas de lock ni de contrôles de publication (projet non installé).
        $process = new Process(['composer', 'validate', '--no-check-lock', '--no-check-publish', '--no-check-all', '--no-interaction'], $dir);
        $process->run();
        self::assertTrue($process->isSuccessful(), "composer.json invalide :\n".$process->getErrorOutput().$process->getOutput());
    }

    /**
     * @return array<string, string>
     */
    private function tree(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[substr($file->getPathname(), \strlen($dir))] = hash_file('sha256', $file->getPathname()) ?: '';
            }
        }
        ksort($files);

        return $files;
    }
}
