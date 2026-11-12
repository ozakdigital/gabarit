<?php

declare(strict_types=1);

namespace Gabarit\Core\Starter;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Generator\GenerationResult;
use Gabarit\Core\Generator\ProjectGenerator;
use Gabarit\Core\Generator\Step\GitInitStep;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Process\ProcessRunner;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Transforme un starter décompressé (gabarit.json + bin/up) en projet complet, dans le même dossier.
 * Appelé par `gabarit apply`, lui-même lancé par `bin/up` dans l'image Docker de Gabarit.
 *
 * Le projet est généré dans un dossier temporaire (create-project exige un dossier vide), puis
 * fusionné dans le dossier du starter. Les secrets sont donc générés sur la machine de l'utilisateur.
 */
final readonly class StarterApplier
{
    /**
     * @param ProjectGenerator $generator générateur configuré SANS git init (fait ici, après la fusion)
     */
    public function __construct(
        private ProjectGenerator $generator,
        private ProcessRunner $process,
        private bool $gitInit = true,
    ) {
    }

    public static function readSpec(string $dir): ProjectSpec
    {
        $file = $dir.'/'.StarterBuilder::SPEC_FILE;
        if (!is_file($file)) {
            throw new GenerationException(\sprintf('Fichier %s introuvable dans %s : ce dossier n\'est pas un starter Gabarit.', StarterBuilder::SPEC_FILE, $dir));
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new GenerationException(\sprintf('%s illisible : %s', StarterBuilder::SPEC_FILE, $e->getMessage()), null, $e);
        }
        if (!\is_array($data) || !\is_array($data['spec'] ?? null)) {
            throw new InvalidSpecException(['spec' => 'Section « spec » manquante dans '.StarterBuilder::SPEC_FILE.'.']);
        }

        /** @var array<string, mixed> $raw */
        $raw = $data['spec'];
        $spec = ProjectSpec::fromArray($raw);

        // Le logo est un fichier du starter : chemin relatif, confiné au dossier.
        $logo = $spec->brand->logoPath;
        if (null !== $logo) {
            if (Path::isAbsolute($logo) || str_contains($logo, '..')) {
                throw new InvalidSpecException(['brand.logoPath' => 'Le logo doit être un fichier du starter (chemin relatif).']);
            }
            $spec = new ProjectSpec($spec->name, $spec->framework, $spec->frameworkVersion, $spec->phpVersion, $spec->database, $spec->frontend, $spec->services, new Brand(
                $spec->brand->displayName,
                $spec->brand->phpNamespace,
                $spec->brand->primaryColor,
                $spec->brand->secondaryColor,
                $dir.'/'.$logo,
            ));
        }

        return $spec;
    }

    public static function isApplied(string $dir): bool
    {
        return is_file($dir.'/composer.json');
    }

    public function apply(string $dir): GenerationResult
    {
        $dir = rtrim(str_replace('\\', '/', Path::makeAbsolute($dir, (string) getcwd())), '/');
        $spec = self::readSpec($dir);
        if (self::isApplied($dir)) {
            throw new GenerationException('Le projet est déjà installé (composer.json présent).');
        }

        $fs = new Filesystem();
        $build = str_replace('\\', '/', sys_get_temp_dir()).'/gabarit-apply-'.bin2hex(random_bytes(6));
        try {
            $result = $this->generator->generate($spec, $build.'/'.$spec->name);
            // Fusion : les fichiers générés remplacent ceux du starter (README.md notamment) ;
            // gabarit.json, bin/up et le logo restent.
            $fs->mirror($result->targetDir, $dir, null, ['override' => true]);
        } finally {
            $fs->remove($build);
        }

        if ($this->gitInit) {
            GitInitStep::initialize($this->process, $dir);
        }

        return new GenerationResult($spec, $dir, $result->steps, $result->warnings);
    }
}
