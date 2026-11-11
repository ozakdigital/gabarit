<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Framework\FrameworkProfile;
use Gabarit\Core\Framework\FrameworkProfiles;
use Gabarit\Core\Frontend\SpaLayouts;
use Gabarit\Core\Matrix\Matrix;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Process\ProcessRunner;
use Gabarit\Core\Secret\SecretGenerator;
use Gabarit\Core\Template\TemplateRenderer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tout ce dont une étape a besoin. Les accès fichiers passent par ici et restent
 * confinés au dossier cible.
 */
final class GenerationContext
{
    /** @var list<string> */
    private array $warnings = [];

    private readonly Filesystem $filesystem;

    public function __construct(
        public readonly ProjectSpec $spec,
        public readonly string $targetDir,
        public readonly Matrix $matrix,
        public readonly TemplateRenderer $renderer,
        public readonly ProcessRunner $process,
        public readonly SecretGenerator $secrets,
        public readonly GenerationOptions $options,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function path(string $relative = ''): string
    {
        $relative = str_replace('\\', '/', $relative);
        if (str_starts_with($relative, '/') || 1 === preg_match('#(^|/)\.\.(/|$)#', $relative)) {
            throw new GenerationException(\sprintf('Chemin hors du projet refusé : « %s ».', $relative));
        }

        return '' === $relative ? $this->targetDir : $this->targetDir.'/'.$relative;
    }

    public function exists(string $relative): bool
    {
        return file_exists($this->path($relative));
    }

    public function read(string $relative): string
    {
        $content = @file_get_contents($this->path($relative));
        if (false === $content) {
            throw new GenerationException(\sprintf('Lecture impossible : %s', $relative));
        }

        return $content;
    }

    public function readOrEmpty(string $relative): string
    {
        return $this->exists($relative) ? $this->read($relative) : '';
    }

    public function write(string $relative, string $content): void
    {
        $this->filesystem->dumpFile($this->path($relative), $content);
    }

    public function copyIn(string $source, string $relative): void
    {
        $this->filesystem->copy($source, $this->path($relative), true);
    }

    /**
     * Rend un template vers un fichier du projet.
     *
     * @param array<string, mixed> $extra
     */
    public function render(string $template, string $relative, array $extra = []): void
    {
        $this->write($relative, $this->renderer->render($template, $this->templateContext() + $extra));
    }

    /**
     * Variables disponibles dans tous les templates.
     *
     * @return array<string, mixed>
     */
    public function templateContext(): array
    {
        $spec = $this->spec;
        $services = [];
        foreach ($spec->services as $service) {
            $services[$service->value] = $this->matrix->service($service);
        }

        $profile = $this->profile();
        $extensions = $profile->phpExtensions($spec->database);
        foreach ($spec->services as $service) {
            if (Service::Redis === $service) {
                $extensions[] = 'redis';
            }
        }

        return [
            'spec' => $spec,
            'name' => $spec->name,
            'brand' => $spec->brand,
            'framework' => $spec->framework->value,
            'framework_def' => $this->matrix->framework($spec->framework),
            'framework_version' => $spec->frameworkVersion,
            'secrets_file' => $profile->secretsFile(),
            'container_env_file' => $profile->containerEnvFile(),
            'php_extensions' => $extensions,
            'check_command' => $profile->checkCommand(),
            'post_up_commands' => $profile->postUpCommands(),
            'db_server' => $this->matrix->database($spec->database)->server ?? false,
            'php_version' => $spec->phpVersion,
            'database' => $spec->database->value,
            'db' => $this->matrix->database($spec->database),
            'frontend' => $spec->frontend->value,
            'frontend_def' => $this->matrix->frontend($spec->frontend),
            'is_spa' => null !== SpaLayouts::for($spec->frontend),
            'spa' => SpaLayouts::for($spec->frontend),
            'vite_plugin' => SpaLayouts::vitePlugin($spec->frontend),
            'services' => $services,
            'docker' => $this->matrix->docker,
            'php_image' => $this->matrix->docker->php($spec->phpVersion),
            'logo_file' => $this->logoFile(),
        ];
    }

    public function profile(): FrameworkProfile
    {
        return FrameworkProfiles::for($this->spec->framework);
    }

    /**
     * Nom public du logo dans le projet généré (« logo.png », « logo.svg ») ou null.
     */
    public function logoFile(): ?string
    {
        $logo = $this->spec->brand->logoPath;
        if (null === $logo) {
            return null;
        }

        return 'logo.'.strtolower(pathinfo($logo, \PATHINFO_EXTENSION));
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
