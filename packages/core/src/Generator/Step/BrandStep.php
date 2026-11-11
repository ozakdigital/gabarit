<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Frontend\SpaLayout;
use Gabarit\Core\Frontend\SpaLayouts;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * 7. Marque : nom Composer, namespace PHP, thème (couleurs), logo.
 */
final class BrandStep implements GeneratorStep
{
    public const int LOGO_MAX_BYTES = 1024 * 1024;

    public function name(): string
    {
        return 'brand';
    }

    public function label(): string
    {
        return 'Application de la marque';
    }

    public function run(GenerationContext $context): void
    {
        $brand = $context->spec->brand;

        $this->updateComposerJson($context);
        if ('App' !== $brand->phpNamespace) {
            $this->rewriteNamespace($context, $brand->phpNamespace);
        }
        // L'autoloader et le cache du framework référencent encore l'ancien namespace s'ils existent déjà.
        if ($context->exists('vendor/autoload.php')) {
            $context->process->run(['composer', 'dump-autoload', '--no-interaction'], $context->targetDir);
        }
        $cache = $context->profile()->cacheDir();
        if (null !== $cache && $context->exists($cache)) {
            (new Filesystem())->remove($context->path($cache));
        }

        $theme = $context->renderer->render('brand/brand.css.twig', $context->templateContext());
        $layout = SpaLayouts::for($context->spec->frontend);
        $publicDir = null !== $layout ? FrontendInstallStep::DIR.'/public' : 'public';

        if (null !== $layout) {
            $this->brandSpa($context, $layout, $theme);
        } else {
            $context->write('public/brand.css', $theme);
        }

        if (null !== $brand->logoPath) {
            self::assertSafeLogo($brand->logoPath);
            $context->copyIn($brand->logoPath, $publicDir.'/'.$context->logoFile());
        }
    }

    /**
     * Thème, import de brand.css dans le point d'entrée et titre de la page. Idempotent.
     */
    private function brandSpa(GenerationContext $context, SpaLayout $layout, string $theme): void
    {
        $dir = FrontendInstallStep::DIR.'/';
        $context->write($dir.'src/brand.css', $theme);

        $styleFile = $dir.$layout->styleFile;
        $source = $context->read($styleFile);
        if (!str_contains($source, $layout->styleImport)) {
            $count = 0;
            if (null !== $layout->styleAfter) {
                $source = preg_replace_callback($layout->styleAfter, static fn (array $m): string => $m[0]."\n".$layout->styleImport, $source, 1, $count) ?? $source;
            }
            if (0 === $count) {
                $source = $layout->styleImport."\n".$source;
            }
            $context->write($styleFile, $source);
        }

        $index = $dir.$layout->indexHtml;
        $title = htmlspecialchars($context->spec->brand->displayName, \ENT_QUOTES | \ENT_HTML5);
        $context->write($index, (string) preg_replace('#<title>.*?</title>#s', '<title>'.$title.'</title>', $context->read($index), 1));
    }

    /**
     * Vérifie un logo : PNG ou SVG, taille limitée, SVG sans script ni gestionnaire d'événement.
     */
    public static function assertSafeLogo(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new GenerationException(\sprintf('Logo introuvable : %s', $path));
        }
        $size = filesize($path);
        if (false === $size || $size > self::LOGO_MAX_BYTES) {
            throw new GenerationException('Le logo dépasse 1 Mo.');
        }

        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        $content = (string) file_get_contents($path);

        if ('png' === $extension) {
            if (!str_starts_with($content, "\x89PNG\r\n\x1a\n")) {
                throw new GenerationException("Le logo n'est pas un PNG valide.");
            }

            return;
        }

        if ('svg' === $extension) {
            if (1 !== preg_match('/<svg[\s>]/i', $content)) {
                throw new GenerationException("Le logo n'est pas un SVG valide.");
            }
            if (1 === preg_match('/<script|<foreignObject|\son[a-z]+\s*=|javascript:|<!ENTITY/i', $content)) {
                throw new GenerationException('Le logo SVG contient du code actif (script, gestionnaire d\'événement…) : refusé.');
            }

            return;
        }

        throw new GenerationException('Formats de logo acceptés : PNG, SVG.');
    }

    private function updateComposerJson(GenerationContext $context): void
    {
        $brand = $context->spec->brand;
        // Décodage en objets : un « {} » vide (ex. require-dev) doit rester un objet, pas devenir « [] ».
        $composer = json_decode($context->read('composer.json'), false, 512, \JSON_THROW_ON_ERROR);
        if (!$composer instanceof \stdClass) {
            throw new GenerationException('composer.json invalide.');
        }

        // On place name/description en tête, comme le recommande Composer.
        $updated = new \stdClass();
        $updated->name = $brand->composerVendor().'/'.$context->spec->name;
        $updated->description = $brand->displayName;
        foreach (get_object_vars($composer) as $key => $value) {
            if (!\in_array($key, ['name', 'description'], true)) {
                $updated->{$key} = $value;
            }
        }
        // Seules les entrées PSR-4 « App\… » sont renommées ; les autres (Database\Factories\, Config\…) restent.
        foreach (['autoload', 'autoload-dev'] as $section) {
            $psr4 = $updated->{$section}->{'psr-4'} ?? null;
            if (!$psr4 instanceof \stdClass) {
                continue;
            }
            $renamed = new \stdClass();
            foreach (get_object_vars($psr4) as $prefix => $path) {
                $prefix = (string) $prefix;
                $renamed->{str_starts_with($prefix, 'App\\') ? $brand->phpNamespace.substr($prefix, 3) : $prefix} = $path;
            }
            $updated->{$section}->{'psr-4'} = $renamed;
        }

        $context->write('composer.json', json_encode($updated, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * Remplace le préfixe « App\ » par le namespace de la marque. Idempotent : un second passage ne trouve plus « App\ ».
     */
    private function rewriteNamespace(GenerationContext $context, string $namespace): void
    {
        $files = [];
        foreach ($context->profile()->namespacePaths() as $relative) {
            $path = $context->path($relative);
            if (is_dir($path)) {
                foreach ((new Finder())->files()->in($path)->name('*.php')->sortByName() as $file) {
                    $files[] = $file->getPathname();
                }
            } elseif (is_file($path)) {
                $files[] = $path;
            }
        }

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            // « namespace App; » (Kernel) et « App\… » partout ailleurs, sans toucher « MyApp\ » ni « Foo\App\ ».
            $updated = (string) preg_replace('/^namespace App;/m', 'namespace '.$namespace.';', $content);
            $updated = (string) preg_replace_callback('/(?<![A-Za-z0-9_\\\\])App\\\\/', static fn (): string => $namespace.'\\', $updated);
            if ($updated !== $content) {
                file_put_contents($file, $updated);
            }
        }
    }
}
