<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Generator;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Frontend\SpaLayouts;
use Gabarit\Core\Generator\Step\BrandStep;
use Gabarit\Core\Generator\Step\CommonFilesStep;
use Gabarit\Core\Generator\Step\DatabaseConfigStep;
use Gabarit\Core\Generator\Step\DockerStep;
use Gabarit\Core\Generator\Step\FrameworkInstallStep;
use Gabarit\Core\Generator\Step\FrontendInstallStep;
use Gabarit\Core\Generator\Step\GitInitStep;
use Gabarit\Core\Generator\Step\SecretsStep;
use Gabarit\Core\Generator\Step\ServicesStep;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Support\EnvFile;
use Gabarit\Core\Tests\Support\FakeProcessRunner;
use Gabarit\Core\Tests\Support\TestCase;
use Symfony\Component\Yaml\Yaml;

final class StepsTest extends TestCase
{
    public function testFrameworkInstallUsesOfficialInstallerWithArgumentArrays(): void
    {
        $process = new FakeProcessRunner();
        $context = $this->context(self::spec(services: [Service::Mailpit]), process: $process);

        (new FrameworkInstallStep())->run($context);

        self::assertSame([
            'composer create-project symfony/skeleton:7.4.* '.$context->targetDir.' --no-interaction --prefer-dist --no-progress',
            'composer config extra.symfony.docker false',
            'composer require symfony/orm-pack symfony/twig-bundle symfony/mailer --no-interaction --no-progress',
        ], $process->commandLines());
        self::assertFileExists($context->path('composer.json'));
    }

    /**
     * @return iterable<string, array{Framework, string, list<non-empty-string>}>
     */
    public static function otherFrameworks(): iterable
    {
        yield 'laravel' => [Framework::Laravel, '13', ['composer create-project laravel/laravel:13.*']];
        yield 'codeigniter' => [Framework::CodeIgniter, '4.7', ['composer create-project codeigniter4/appstarter:4.7.*']];
        yield 'slim' => [Framework::Slim, '4', ['composer create-project slim/slim-skeleton:4.*', 'composer require php-di/php-di:^7.1 --with-all-dependencies --no-interaction --no-progress']];
    }

    /**
     * @param list<non-empty-string> $expected début de chaque commande attendue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('otherFrameworks')]
    public function testOtherFrameworksUseTheirOfficialSkeleton(Framework $framework, string $version, array $expected): void
    {
        $process = new FakeProcessRunner();
        $context = $this->context(self::spec(frontend: Frontend::None, brand: new Brand('X'), version: $version, framework: $framework), process: $process);

        (new FrameworkInstallStep())->run($context);

        $lines = $process->commandLines();
        self::assertCount(\count($expected), $lines);
        foreach ($expected as $i => $start) {
            self::assertStringStartsWith($start, $lines[$i]);
        }
        self::assertFileExists($context->path('composer.json'));
        self::assertFileDoesNotExist($context->path('docker-compose.yml'), 'Le compose du squelette Slim doit être retiré');
    }

    public function testFrameworkInstallSkipsCreateProjectWhenAlreadyInstalled(): void
    {
        $process = new FakeProcessRunner();
        $context = $this->context(self::spec(frontend: Frontend::Vue), process: $process);
        (new FrameworkInstallStep())->run($context);
        $process->calls = [];

        (new FrameworkInstallStep())->run($context);

        self::assertSame([
            'composer config extra.symfony.docker false',
            'composer require symfony/orm-pack --no-interaction --no-progress',
        ], $process->commandLines());
    }

    public function testFrameworkInstallRefusesNonEmptyTarget(): void
    {
        $context = $this->context(self::spec());
        mkdir($context->targetDir, 0o775, true);
        file_put_contents($context->targetDir.'/important.txt', 'ne pas écraser');

        $this->expectException(GenerationException::class);
        (new FrameworkInstallStep())->run($context);
    }

    public function testVueFrontendRunsPinnedCreateVite(): void
    {
        $process = new FakeProcessRunner();
        $context = $this->context(self::spec(frontend: Frontend::Vue), process: $process);

        (new FrontendInstallStep())->run($context);

        self::assertSame(['npm exec --yes -- create-vite@9.3.4 frontend --template vue-ts --no-interactive --no-immediate'], $process->commandLines());
        self::assertStringContainsString("'/api'", $context->read('frontend/vite.config.ts'));
        self::assertFileDoesNotExist($context->path('frontend/src/components/HelloWorld.vue'));
        self::assertSame('mon-app-frontend', json_decode($context->read('frontend/package.json'), true)['name']);
    }

    /**
     * @return iterable<string, array{Frontend, string}>
     */
    public static function spaInstallers(): iterable
    {
        yield 'react' => [Frontend::React, 'npm exec --yes -- create-vite@9.3.4 frontend --template react-ts --no-interactive --no-immediate'];
        yield 'svelte' => [Frontend::Svelte, 'npm exec --yes -- create-vite@9.3.4 frontend --template svelte-ts --no-interactive --no-immediate'];
        yield 'solid' => [Frontend::Solid, 'npm exec --yes -- create-vite@9.3.4 frontend --template solid-ts --no-interactive --no-immediate'];
        yield 'preact' => [Frontend::Preact, 'npm exec --yes -- create-vite@9.3.4 frontend --template preact-ts --no-interactive --no-immediate'];
        yield 'angular' => [Frontend::Angular, 'npm exec --yes -- @angular/cli@22.2.6 new frontend --directory=frontend --defaults --interactive=false --skip-git --skip-install --style=css --ssr=false --routing=false --ai-config=none'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('spaInstallers')]
    public function testSpaFrontendsUseTheirPinnedOfficialInstaller(Frontend $frontend, string $expected): void
    {
        $process = new FakeProcessRunner();
        $context = $this->context(self::spec(frontend: $frontend), process: $process);

        (new FrontendInstallStep())->run($context);
        (new FrontendInstallStep())->run($context);

        self::assertSame([$expected], $process->commandLines(), "L'installeur ne doit tourner qu'une fois");
        $layout = SpaLayouts::for($frontend);
        self::assertNotNull($layout);
        foreach ($layout->renders as $target) {
            self::assertFileExists($context->path('frontend/'.$target));
        }
        foreach ($layout->remove as $removed) {
            self::assertFileDoesNotExist($context->path('frontend/'.$removed));
        }
        $component = $context->read('frontend/'.array_values($layout->renders)[0]);
        self::assertStringContainsString('/api/health', $component);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('spaInstallers')]
    public function testBrandOnEverySpaIsIdempotent(Frontend $frontend, string $installer): void
    {
        $context = $this->installedContext(self::spec(frontend: $frontend, brand: new Brand('Acme & Co', primaryColor: '#00ff00')));
        $layout = SpaLayouts::for($frontend);
        self::assertNotNull($layout);

        (new BrandStep())->run($context);
        (new BrandStep())->run($context);

        self::assertSame(1, substr_count($context->read('frontend/'.$layout->styleFile), $layout->styleImport));
        self::assertStringContainsString('<title>Acme &amp; Co</title>', $context->read('frontend/'.$layout->indexHtml));
        self::assertStringContainsString('#00ff00', $context->read('frontend/src/brand.css'));
    }

    public function testAngularGetsProxyAndBrandedSpec(): void
    {
        $context = $this->installedContext(self::spec(frontend: Frontend::Angular, brand: new Brand('Boutique Démo')));

        (new FrontendInstallStep())->run($context);

        $proxy = json_decode($context->read('frontend/proxy.conf.json'), true);
        self::assertSame('http://nginx', $proxy['/api']['target']);
        self::assertStringContainsString('toContain("Boutique Démo")', $context->read('frontend/src/app/app.spec.ts'));
        self::assertStringContainsString('{{ health() }}', $context->read('frontend/src/app/app.html'));
        self::assertStringNotContainsString('styleUrl', $context->read('frontend/src/app/app.ts'));
    }

    public function testTwigFrontendWritesHomePage(): void
    {
        $context = $this->installedContext(self::spec());

        (new FrontendInstallStep())->run($context);

        self::assertStringContainsString('Mon App', $context->read('templates/home/index.html.twig'));
        self::assertStringContainsString('/brand.css', $context->read('templates/base.html.twig'));
        self::assertStringContainsString("#[Route('/'", $context->read('src/Controller/HomeController.php'));
    }

    public function testDatabaseConfigKeepsPasswordOutOfDotEnv(): void
    {
        $context = $this->installedContext(self::spec(database: Database::MySql));

        (new DatabaseConfigStep())->run($context);

        $env = EnvFile::parse($context->read('.env'));
        self::assertSame('mysql://app:!ChangeMe!@database:3306/app?serverVersion=8.4&charset=utf8mb4', $env['DATABASE_URL']);
        self::assertSame(1, substr_count($context->read('.env'), 'DATABASE_URL='));
        self::assertFileExists($context->path('src/Controller/HealthController.php'));
    }

    public function testServicesAreAddedOnceToComposeAndEnv(): void
    {
        $context = $this->installedContext(self::spec(services: [Service::Redis, Service::Mailpit]));
        (new DockerStep())->run($context);

        (new ServicesStep())->run($context);
        (new ServicesStep())->run($context);

        $compose = Yaml::parse($context->read('compose.yaml'));
        self::assertIsArray($compose);
        self::assertSame(['app', 'nginx', 'database', 'mailpit', 'redis'], array_keys($compose['services']));
        self::assertSame(1, substr_count($context->read('compose.yaml'), '  redis:'));

        $env = EnvFile::parse($context->read('.env'));
        self::assertSame('redis://redis:6379', $env['REDIS_URL']);
        self::assertSame('smtp://mailpit:1025', $env['MAILER_DSN']);
    }

    public function testBrandRewritesNamespaceEverywhereAndIsIdempotent(): void
    {
        $context = $this->installedContext(self::spec(brand: new Brand('Acme Shop', 'Acme\\Shop', '#ff0000')));
        (new FrontendInstallStep())->run($context);
        (new DatabaseConfigStep())->run($context);

        (new BrandStep())->run($context);
        $snapshot = $this->snapshot($context->targetDir);
        (new BrandStep())->run($context);

        self::assertSame($snapshot, $this->snapshot($context->targetDir), 'BrandStep doit être idempotent');

        // Le squelette contient « "require-dev": {} » : il doit rester un objet (sinon Composer refuse le fichier).
        self::assertStringContainsString('"require-dev": {}', $context->read('composer.json'));
        $composer = json_decode($context->read('composer.json'), true);
        self::assertSame('acme/mon-app', $composer['name']);
        self::assertSame('name', array_key_first($composer));
        self::assertSame('Acme Shop', $composer['description']);
        self::assertSame(['Acme\\Shop\\' => 'src/'], $composer['autoload']['psr-4']);

        self::assertStringContainsString('namespace Acme\\Shop;', $context->read('src/Kernel.php'));
        self::assertStringContainsString('namespace Acme\\Shop\\Controller;', $context->read('src/Controller/HealthController.php'));
        self::assertStringContainsString('use Acme\\Shop\\Kernel;', $context->read('public/index.php'));
        self::assertStringContainsString('use Acme\\Shop\\Kernel;', $context->read('bin/console'));
        self::assertStringContainsString('Acme\\Shop\\:', $context->read('config/services.yaml'));
        self::assertStringContainsString("prefix: 'Acme\\Shop\\Entity'", $context->read('config/packages/doctrine.yaml'));
        // Fichier de référence généré par Symfony : jamais modifié.
        self::assertStringContainsString("'App\\\\'", $context->read('config/reference.php'));

        self::assertStringContainsString('--brand-primary: #ff0000;', $context->read('public/brand.css'));
    }

    public function testBrandRefreshesAutoloaderAndCacheWhenDependenciesAreInstalled(): void
    {
        $process = new FakeProcessRunner();
        $context = $this->context(self::spec(brand: new Brand('Acme', 'Acme')), process: $process);
        (new FrameworkInstallStep())->run($context);
        $context->write('vendor/autoload.php', '<?php');
        $context->write('var/cache/dev/App_KernelDevDebugContainer.php', '<?php');
        $process->calls = [];

        (new BrandStep())->run($context);

        self::assertSame(['composer dump-autoload --no-interaction'], $process->commandLines());
        self::assertDirectoryDoesNotExist($context->path('var/cache'));
    }

    public function testBrandOnVueFrontend(): void
    {
        $logo = $this->tempDir().'/logo.svg';
        file_put_contents($logo, '<svg xmlns="http://www.w3.org/2000/svg"><circle r="4"/></svg>');
        $context = $this->installedContext(self::spec(frontend: Frontend::Vue, brand: new Brand("L'Atelier", logoPath: $logo)));

        (new BrandStep())->run($context);
        (new BrandStep())->run($context);

        self::assertSame(1, substr_count($context->read('frontend/src/main.ts'), "import './brand.css'"));
        self::assertStringContainsString('<title>L&apos;Atelier</title>', $context->read('frontend/index.html'));
        self::assertFileExists($context->path('frontend/public/logo.svg'));
        self::assertFileExists($context->path('frontend/src/brand.css'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsafeLogos(): iterable
    {
        yield 'script' => ['logo.svg', '<svg><script>alert(1)</script></svg>'];
        yield 'onload' => ['logo.svg', '<svg onload="alert(1)"></svg>'];
        yield 'javascript' => ['logo.svg', '<svg><a href="javascript:alert(1)"/></svg>'];
        yield 'entité XML' => ['logo.svg', '<!DOCTYPE x [<!ENTITY a "b">]><svg></svg>'];
        yield 'faux PNG' => ['logo.png', '<?php echo 1;'];
        yield 'extension interdite' => ['logo.php', '<?php echo 1;'];
        yield 'pas un SVG' => ['logo.svg', 'bonjour'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeLogos')]
    public function testUnsafeLogosAreRejected(string $file, string $content): void
    {
        $path = $this->tempDir().'/'.$file;
        file_put_contents($path, $content);

        $this->expectException(GenerationException::class);
        BrandStep::assertSafeLogo($path);
    }

    public function testSecretsAreRandomAndPreservedOnRerun(): void
    {
        $context = $this->installedContext(self::spec(database: Database::MySql));

        (new SecretsStep())->run($context);
        $first = EnvFile::parse($context->read('.env.local'));
        (new SecretsStep())->run($context);
        $second = EnvFile::parse($context->read('.env.local'));

        self::assertSame($first, $second);
        self::assertSame(['APP_SECRET', 'DB_PASSWORD', 'DB_ROOT_PASSWORD', 'DATABASE_URL'], array_keys($first));
        self::assertSame(32, \strlen($first['APP_SECRET']));
        self::assertStringContainsString(':'.$first['DB_PASSWORD'].'@database:3306', $first['DATABASE_URL']);
        self::assertStringNotContainsString($first['DB_PASSWORD'], $context->read('.env'));
    }

    public function testCommonFilesMergeGitignoreOnce(): void
    {
        $context = $this->installedContext(self::spec());

        (new CommonFilesStep())->run($context);
        (new CommonFilesStep())->run($context);

        $gitignore = $context->read('.gitignore');
        self::assertStringContainsString('/.env.local', $gitignore);
        self::assertSame(1, substr_count($gitignore, '###> gabarit ###'));
        self::assertFileExists($context->path('.github/workflows/ci.yml'));
        self::assertFileExists($context->path('Makefile'));
    }

    public function testGitInitUsesFallbackIdentityOnlyWhenNeeded(): void
    {
        $process = new FakeProcessRunner(gitIdentity: false);
        $context = $this->context(self::spec(), process: $process, gitInit: true);
        mkdir($context->targetDir, 0o775, true);

        (new GitInitStep())->run($context);
        (new GitInitStep())->run($context);

        self::assertSame([
            'git init --quiet --initial-branch=main',
            'git add --all',
            'git config user.email',
            'git -c user.name=Gabarit -c user.email=gabarit@localhost commit --quiet -m Initial commit (Gabarit)',
        ], $process->commandLines());
    }

    public function testGitInitCanBeDisabled(): void
    {
        $process = new FakeProcessRunner();
        $context = $this->context(self::spec(), process: $process, gitInit: false);

        (new GitInitStep())->run($context);

        self::assertSame([], $process->calls);
    }

    public function testContextRefusesPathsOutsideTheProject(): void
    {
        $this->expectException(GenerationException::class);
        $this->context(self::spec())->write('../evil.txt', 'x');
    }

    /**
     * @return array<string, string>
     */
    private function snapshot(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[substr($file->getPathname(), \strlen($dir))] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($files);

        return $files;
    }
}
