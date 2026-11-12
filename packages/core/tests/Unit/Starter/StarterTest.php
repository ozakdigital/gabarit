<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Starter;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Gabarit;
use Gabarit\Core\Generator\GenerationOptions;
use Gabarit\Core\Generator\ProjectGeneratorFactory;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Starter\StarterApplier;
use Gabarit\Core\Starter\StarterBuilder;
use Gabarit\Core\Tests\Support\FakeProcessRunner;
use Gabarit\Core\Tests\Support\FixedSecretGenerator;
use Gabarit\Core\Tests\Support\TestCase;

final class StarterTest extends TestCase
{
    public function testStarterContainsOnlyTheSpecAndTheLaunchers(): void
    {
        $files = (new StarterBuilder(self::matrix()))->files(self::spec(frontend: Frontend::Vue, services: [Service::Mailpit]));

        self::assertSame(['README.md', 'bin/up', 'bin/up.cmd', 'gabarit.json'], array_keys($files));
        self::assertTrue($files['bin/up']['executable']);

        $json = json_decode($files['gabarit.json']['content'], true);
        self::assertSame(Gabarit::VERSION, $json['gabarit']);
        self::assertSame(Gabarit::image(), $json['image']);
        self::assertSame('vue', $json['spec']['frontend']);

        // Les scripts installent via l'image, puis démarrent avec le fichier de secrets du framework.
        self::assertStringContainsString('"$IMAGE" apply', $files['bin/up']['content']);
        self::assertStringContainsString('docker compose --env-file .env.local', $files['bin/up']['content']);
        self::assertStringContainsString("\r\n", $files['bin/up.cmd']['content']);
        self::assertStringNotContainsString("\r\n", $files['bin/up']['content']);
        self::assertStringContainsString('Mailpit', $files['bin/up']['content']);
    }

    public function testLaravelStarterRunsMigrationsAndUsesDotEnv(): void
    {
        $files = (new StarterBuilder(self::matrix()))->files(self::spec(frontend: Frontend::Blade, version: '13', framework: Framework::Laravel));

        self::assertStringContainsString('docker compose --env-file .env', $files['bin/up']['content']);
        self::assertStringContainsString('exec -T app php artisan migrate --force', $files['bin/up']['content']);
        self::assertStringContainsString('php artisan migrate --force', $files['bin/up.cmd']['content']);
    }

    public function testInvalidChoicesAreRejectedBeforeBuildingAnything(): void
    {
        $this->expectException(InvalidSpecException::class);
        (new StarterBuilder(self::matrix()))->files(self::spec(php: '7.4'));
    }

    public function testLogoIsEmbeddedAndReferencedRelatively(): void
    {
        $logo = $this->tempDir().'/upload-123.svg';
        file_put_contents($logo, '<svg xmlns="http://www.w3.org/2000/svg"><circle r="4"/></svg>');

        $files = (new StarterBuilder(self::matrix()))->files(self::spec(brand: new Brand('Acme', logoPath: $logo)));

        self::assertArrayHasKey('logo.svg', $files);
        self::assertSame('logo.svg', json_decode($files['gabarit.json']['content'], true)['spec']['brand']['logoPath']);
    }

    public function testZipIsDeterministicWithExecutableLauncher(): void
    {
        $builder = new StarterBuilder(self::matrix());
        $a = $this->tempDir().'/a.zip';
        $b = $this->tempDir().'/b.zip';
        $builder->zip(self::spec(name: 'boutique'), $a);
        $builder->zip(self::spec(name: 'boutique'), $b);

        self::assertSame(hash_file('sha256', $a), hash_file('sha256', $b));

        $zip = new \ZipArchive();
        $zip->open($a);
        self::assertNotFalse($zip->locateName('boutique/bin/up'));
        $zip->getExternalAttributesName('boutique/bin/up', $os, $attributes);
        self::assertSame(0o755, ($attributes >> 16) & 0o777);
        $zip->close();
    }

    public function testApplyTurnsTheStarterIntoTheFullProjectInPlace(): void
    {
        $dir = $this->unpack(self::spec(name: 'boutique', database: Database::MySql, frontend: Frontend::React, brand: new Brand('Boutique', 'Acme')));
        $process = new FakeProcessRunner();

        $result = $this->applier($process)->apply($dir);

        self::assertSame($dir, $result->targetDir);
        self::assertCount(10, $result->steps);
        foreach (['composer.json', 'compose.yaml', 'Makefile', '.env.local', 'frontend/src/App.tsx', 'src/Controller/HealthController.php'] as $file) {
            self::assertFileExists($dir.'/'.$file);
        }
        // Le starter reste utilisable : bin/up démarre désormais le projet installé.
        self::assertFileExists($dir.'/gabarit.json');
        self::assertFileExists($dir.'/bin/up');
        self::assertFileExists($dir.'/bin/console');
        self::assertStringContainsString('namespace Acme\\Controller;', (string) file_get_contents($dir.'/src/Controller/HealthController.php'));
        // Le README du projet complet remplace celui du starter.
        self::assertStringContainsString('make up', (string) file_get_contents($dir.'/README.md'));
        // git init a lieu dans le dossier final, après la fusion.
        self::assertContains('git init --quiet --initial-branch=main', $process->commandLines());
        $lastCall = end($process->calls);
        self::assertNotFalse($lastCall);
        self::assertSame($dir, $lastCall['cwd']);
    }

    public function testApplyRefusesAnAlreadyInstalledProject(): void
    {
        $dir = $this->unpack(self::spec(name: 'deja'));
        $this->applier(new FakeProcessRunner())->apply($dir);

        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('déjà installé');
        $this->applier(new FakeProcessRunner())->apply($dir);
    }

    public function testApplyRequiresAStarter(): void
    {
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('gabarit.json');
        $this->applier(new FakeProcessRunner())->apply($this->tempDir());
    }

    public function testLogoPathCannotEscapeTheStarter(): void
    {
        $dir = $this->unpack(self::spec(name: 'evil'));
        $json = json_decode((string) file_get_contents($dir.'/gabarit.json'), true);
        $json['spec']['brand']['logoPath'] = '../../etc/passwd.png';
        file_put_contents($dir.'/gabarit.json', json_encode($json));

        $this->expectException(InvalidSpecException::class);
        StarterApplier::readSpec($dir);
    }

    private function applier(FakeProcessRunner $process): StarterApplier
    {
        $generator = ProjectGeneratorFactory::create(self::matrix(), $process, new GenerationOptions(gitInit: false), secrets: new FixedSecretGenerator());

        return new StarterApplier($generator, $process);
    }

    private function unpack(\Gabarit\Core\Model\ProjectSpec $spec): string
    {
        $dir = $this->tempDir().'/'.$spec->name;
        foreach ((new StarterBuilder(self::matrix()))->files($spec) as $path => ['content' => $content]) {
            @mkdir(\dirname($dir.'/'.$path), 0o775, true);
            file_put_contents($dir.'/'.$path, $content);
        }

        return $dir;
    }
}
