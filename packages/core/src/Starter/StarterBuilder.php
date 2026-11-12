<?php

declare(strict_types=1);

namespace Gabarit\Core\Starter;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Framework\FrameworkProfiles;
use Gabarit\Core\Frontend\SpaLayouts;
use Gabarit\Core\Gabarit;
use Gabarit\Core\Generator\Step\BrandStep;
use Gabarit\Core\Matrix\Matrix;
use Gabarit\Core\Matrix\SpecValidator;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Template\TemplateRenderer;

/**
 * Construit le « starter » téléchargeable (modèle Spring Initializr) : une archive légère qui contient
 * les choix (gabarit.json), le logo éventuel et les scripts bin/up. Rien n'est installé côté serveur :
 * `bin/up` lance l'image Gabarit sur la machine de l'utilisateur, qui installe le framework
 * (StarterApplier), génère les secrets localement, puis démarre le projet.
 */
final readonly class StarterBuilder
{
    public const string SPEC_FILE = 'gabarit.json';

    /** Date fixe des entrées zip (archives identiques pour les mêmes choix). */
    private const int FIXED_MTIME = 315532800;

    public function __construct(
        private Matrix $matrix,
        private TemplateRenderer $renderer = new TemplateRenderer(),
        private string $image = '',
    ) {
    }

    /**
     * Contenu du starter : chemin relatif => [contenu, exécutable].
     *
     * @return array<string, array{content: string, executable: bool}>
     */
    public function files(ProjectSpec $spec): array
    {
        (new SpecValidator($this->matrix))->assertValid($spec);

        $files = [];
        $logoPath = $spec->brand->logoPath;
        if (null !== $logoPath) {
            BrandStep::assertSafeLogo($logoPath);
            $logoName = 'logo.'.strtolower(pathinfo($logoPath, \PATHINFO_EXTENSION));
            $files[$logoName] = ['content' => (string) file_get_contents($logoPath), 'executable' => false];
            // Dans le starter, le logo est un fichier voisin de gabarit.json.
            $spec = new ProjectSpec($spec->name, $spec->framework, $spec->frameworkVersion, $spec->phpVersion, $spec->database, $spec->frontend, $spec->services, new Brand(
                $spec->brand->displayName,
                $spec->brand->phpNamespace,
                $spec->brand->primaryColor,
                $spec->brand->secondaryColor,
                $logoName,
            ));
        }

        $context = $this->context($spec);
        $files[self::SPEC_FILE] = ['content' => json_encode([
            'gabarit' => Gabarit::VERSION,
            'image' => $context['image'],
            'spec' => $spec->toArray(),
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n", 'executable' => false];
        $files['bin/up'] = ['content' => $this->renderer->render('starter/up.sh.twig', $context), 'executable' => true];
        // Fins de ligne Windows : cmd.exe interprète mal certains scripts .cmd en LF.
        $files['bin/up.cmd'] = ['content' => str_replace("\n", "\r\n", $this->renderer->render('starter/up.cmd.twig', $context)), 'executable' => false];
        $files['README.md'] = ['content' => $this->renderer->render('starter/README.md.twig', $context), 'executable' => false];
        ksort($files);

        return $files;
    }

    /**
     * Écrit l'archive zip du starter (dossier racine = nom du projet).
     */
    public function zip(ProjectSpec $spec, string $zipPath): void
    {
        $files = $this->files($spec);

        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new GenerationException(\sprintf('Impossible de créer l\'archive %s.', $zipPath));
        }
        foreach ($files as $path => ['content' => $content, 'executable' => $executable]) {
            $name = $spec->name.'/'.$path;
            $zip->addFromString($name, $content);
            $zip->setMtimeName($name, self::FIXED_MTIME);
            $zip->setExternalAttributesName($name, \ZipArchive::OPSYS_UNIX, ($executable ? 0o100755 : 0o100644) << 16);
        }
        if (!$zip->close()) {
            throw new GenerationException(\sprintf('Échec de l\'écriture de l\'archive %s.', $zipPath));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function context(ProjectSpec $spec): array
    {
        $profile = FrameworkProfiles::for($spec->framework);
        $services = [];
        foreach ($spec->services as $service) {
            $services[$service->value] = $this->matrix->service($service);
        }

        return [
            'spec' => $spec,
            'name' => $spec->name,
            'brand' => $spec->brand,
            'framework_def' => $this->matrix->framework($spec->framework),
            'framework_version' => $spec->frameworkVersion,
            'php_version' => $spec->phpVersion,
            'db' => $this->matrix->database($spec->database),
            'frontend_def' => $this->matrix->frontend($spec->frontend),
            'is_spa' => null !== SpaLayouts::for($spec->frontend),
            'services' => $services,
            'secrets_file' => $profile->secretsFile(),
            'post_up_commands' => $profile->postUpCommands(),
            'image' => '' !== $this->image ? $this->image : Gabarit::image(),
            'gabarit_version' => Gabarit::VERSION,
        ];
    }
}
