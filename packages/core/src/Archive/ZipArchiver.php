<?php

declare(strict_types=1);

namespace Gabarit\Core\Archive;

use Gabarit\Core\Exception\GenerationException;

/**
 * Crée l'archive zip d'un projet généré (téléchargement côté web).
 * Déterministe : entrées triées, date fixe. Les dépendances installées sont exclues ; .env.local est
 * inclus car il porte les secrets propres à ce projet, nécessaires à `make up`.
 */
final readonly class ZipArchiver
{
    /** Date fixe des entrées (1980-01-01, minimum du format zip). */
    private const int FIXED_MTIME = 315532800;

    /**
     * @param list<string> $excluded préfixes de chemins relatifs exclus
     */
    public function __construct(
        private array $excluded = ['vendor/', 'var/', 'frontend/node_modules/', '.git/'],
    ) {
    }

    /**
     * @param string $rootName dossier racine dans l'archive (nom du projet)
     */
    public function archive(string $sourceDir, string $zipPath, string $rootName): void
    {
        if (!is_dir($sourceDir)) {
            throw new GenerationException(\sprintf('Dossier introuvable : %s', $sourceDir));
        }

        $sourceDir = rtrim(str_replace('\\', '/', $sourceDir), '/');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = substr(str_replace('\\', '/', $file->getPathname()), \strlen($sourceDir) + 1);
            if (!$this->isExcluded($relative)) {
                $files[$relative] = $file->getPathname();
            }
        }
        ksort($files, \SORT_STRING);

        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new GenerationException(\sprintf('Impossible de créer l\'archive %s.', $zipPath));
        }
        foreach ($files as $relative => $path) {
            $name = $rootName.'/'.$relative;
            $zip->addFile($path, $name);
            $zip->setMtimeName($name, self::FIXED_MTIME);
            if (is_executable($path)) {
                $zip->setExternalAttributesName($name, \ZipArchive::OPSYS_UNIX, 0o100755 << 16);
            }
        }
        if (!$zip->close()) {
            throw new GenerationException(\sprintf('Échec de l\'écriture de l\'archive %s.', $zipPath));
        }
    }

    private function isExcluded(string $relative): bool
    {
        foreach ($this->excluded as $prefix) {
            if ($relative === rtrim($prefix, '/') || str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
