<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Support;

use Gabarit\Core\Frontend\SpaLayouts;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GenerationOptions;
use Gabarit\Core\Matrix\Matrix;
use Gabarit\Core\Matrix\MatrixLoader;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Template\TemplateRenderer;
use Symfony\Component\Filesystem\Filesystem;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    private static ?Matrix $matrix = null;

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDirs);
        $this->tempDirs = [];
    }

    protected static function matrix(): Matrix
    {
        return self::$matrix ??= (new MatrixLoader())->load();
    }

    protected function tempDir(): string
    {
        $dir = str_replace('\\', '/', sys_get_temp_dir()).'/gabarit-test-'.bin2hex(random_bytes(6));
        mkdir($dir, 0o775, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    /**
     * @param list<Service> $services
     */
    protected static function spec(
        string $name = 'mon-app',
        string $php = '8.3',
        Database $database = Database::PostgreSql,
        Frontend $frontend = Frontend::Twig,
        array $services = [],
        ?Brand $brand = null,
        string $version = '7.4',
        Framework $framework = Framework::Symfony,
    ): ProjectSpec {
        return new ProjectSpec($name, $framework, $version, $php, $database, $frontend, $services, $brand ?? Brand::fromProjectName($name));
    }

    protected function context(ProjectSpec $spec, ?string $dir = null, ?FakeProcessRunner $process = null, bool $gitInit = false): GenerationContext
    {
        return new GenerationContext(
            $spec,
            $dir ?? $this->tempDir().'/'.$spec->name,
            self::matrix(),
            new TemplateRenderer(),
            $process ?? new FakeProcessRunner(),
            new FixedSecretGenerator(),
            new GenerationOptions(gitInit: $gitInit),
        );
    }

    /**
     * Contexte dont le dossier contient déjà le squelette du framework (comme après FrameworkInstallStep).
     */
    protected function installedContext(ProjectSpec $spec): GenerationContext
    {
        $context = $this->context($spec);
        $package = self::matrix()->framework($spec->framework)?->version($spec->frameworkVersion)->package ?? 'symfony/skeleton';
        (new Filesystem())->mirror(FakeProcessRunner::fixturesDir().'/'.FakeProcessRunner::skeletonOf($package), $context->targetDir);
        if (null !== SpaLayouts::for($spec->frontend)) {
            (new Filesystem())->mirror(FakeProcessRunner::fixturesDir().'/frontend/'.$spec->frontend->value, $context->targetDir.'/frontend');
        }

        return $context;
    }
}
