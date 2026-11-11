<?php

declare(strict_types=1);

namespace Gabarit\Core\Template;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

/**
 * Rendu des fichiers générés. Pas d'échappement HTML automatique (on produit du code,
 * du YAML, des Dockerfile…) : chaque template utilise le filtre adapté à son contexte.
 */
final readonly class TemplateRenderer
{
    private Environment $twig;

    public function __construct(?string $templatesDir = null)
    {
        $this->twig = new Environment(new FilesystemLoader($templatesDir ?? self::defaultDir()), [
            'autoescape' => false,
            'strict_variables' => true,
            'cache' => false,
        ]);

        // Chaîne JSON : valide aussi comme scalaire YAML entre guillemets.
        $this->twig->addFilter(new TwigFilter('json', static fn (mixed $value): string => json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)));
    }

    public static function defaultDir(): string
    {
        return \dirname(__DIR__, 2).'/templates';
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    public function exists(string $template): bool
    {
        return $this->twig->getLoader()->exists($template);
    }

    /**
     * @return list<string> chemins relatifs de tous les templates (.twig)
     */
    public static function all(?string $templatesDir = null): array
    {
        $dir = $templatesDir ?? self::defaultDir();
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && str_ends_with($file->getFilename(), '.twig')) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), \strlen($dir) + 1));
            }
        }
        sort($files);

        return $files;
    }
}
