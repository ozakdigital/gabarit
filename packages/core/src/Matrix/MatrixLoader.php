<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

use Gabarit\Core\Exception\MatrixException;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\Service;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Charge et valide config/matrix.yaml. Toute incohérence est une erreur, jamais ignorée.
 */
final class MatrixLoader
{
    public static function defaultPath(): string
    {
        return \dirname(__DIR__, 4).'/config/matrix.yaml';
    }

    public function load(?string $path = null): Matrix
    {
        $path ??= self::defaultPath();
        if (!is_file($path)) {
            throw new MatrixException(\sprintf('Matrice introuvable : %s', $path));
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new MatrixException(\sprintf('Matrice illisible (%s) : %s', $path, $e->getMessage()), 0, $e);
        }

        if (!\is_array($data)) {
            throw new MatrixException('La matrice doit être un objet YAML.');
        }

        /** @var array<string, mixed> $data */
        return $this->fromArray($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): Matrix
    {
        $databases = [];
        foreach ($this->map($data, 'databases') as $key => $raw) {
            $database = Database::tryFrom($key) ?? throw new MatrixException(\sprintf('Base de données inconnue du code : « %s ».', $key));
            $ctx = "databases.$key";
            $server = $this->bool($raw, 'server', $ctx);
            $databases[$key] = $server
                ? new DatabaseDefinition(
                    $database,
                    $this->str($raw, 'label', $ctx),
                    true,
                    $this->str($raw, 'image', $ctx),
                    $this->int($raw, 'port', $ctx),
                    $this->str($raw, 'server_version', $ctx),
                )
                : new DatabaseDefinition($database, $this->str($raw, 'label', $ctx), false);
        }

        $frontends = [];
        foreach ($this->map($data, 'frontends') as $key => $raw) {
            $frontend = Frontend::tryFrom($key) ?? throw new MatrixException(\sprintf('Front inconnu du code : « %s ».', $key));
            $ctx = "frontends.$key";
            $kind = FrontendKind::tryFrom($this->str($raw, 'kind', $ctx))
                ?? throw new MatrixException(\sprintf('%s.kind : attendu server, vite ou angular.', $ctx));
            $installer = $this->optionalStr($raw, 'installer', $ctx);
            $template = $this->optionalStr($raw, 'template', $ctx);

            if ($kind->isSpa() && (null === $installer || 1 !== preg_match('/^(@[a-z0-9-]+\/)?[a-z0-9-]+@\d+\.\d+\.\d+$/', $installer))) {
                throw new MatrixException(\sprintf('%s.installer : paquet npm à version exacte attendu (ex. create-vite@9.3.4).', $ctx));
            }
            if (FrontendKind::Vite === $kind && null === $template) {
                throw new MatrixException(\sprintf('%s.template : modèle create-vite obligatoire.', $ctx));
            }

            $frontends[$key] = new FrontendDefinition($frontend, $this->str($raw, 'label', $ctx), $kind, $installer, $template);
        }

        $services = [];
        foreach ($this->map($data, 'services') as $key => $raw) {
            $service = Service::tryFrom($key) ?? throw new MatrixException(\sprintf('Service inconnu du code : « %s ».', $key));
            $services[$key] = new ServiceDefinition(
                $service,
                $this->str($raw, 'label', "services.$key"),
                $this->str($raw, 'image', "services.$key"),
                $this->int($raw, 'port', "services.$key"),
            );
        }

        $frameworks = [];
        foreach ($this->map($data, 'frameworks') as $key => $raw) {
            $framework = Framework::tryFrom($key) ?? throw new MatrixException(\sprintf('Framework inconnu du code : « %s ».', $key));
            $ctx = "frameworks.$key";

            $versions = [];
            foreach ($this->map($raw, 'versions', $ctx) as $version => $rawVersion) {
                $version = (string) $version;
                if (1 !== preg_match('/^\d+(\.\d+){0,2}$/', $version)) {
                    throw new MatrixException(\sprintf('%s.versions : version invalide « %s ».', $ctx, $version));
                }
                $php = $this->stringList($rawVersion, 'php', "$ctx.versions.$version");
                foreach ($php as $phpVersion) {
                    if (1 !== preg_match('/^\d+\.\d+$/', $phpVersion)) {
                        throw new MatrixException(\sprintf('%s.versions.%s : version PHP invalide « %s ».', $ctx, $version, $phpVersion));
                    }
                }
                $versions[$version] = new FrameworkVersion(
                    $version,
                    $php,
                    $this->str($rawVersion, 'package', "$ctx.versions.$version"),
                    $this->str($rawVersion, 'constraint', "$ctx.versions.$version"),
                );
            }

            $frameworkDatabases = [];
            foreach ($this->stringList($raw, 'databases', $ctx) as $name) {
                $frameworkDatabases[] = ($databases[$name] ?? throw new MatrixException(\sprintf('%s : base « %s » non déclarée dans « databases ».', $ctx, $name)))->database;
            }

            $frameworkFrontends = [];
            foreach ($this->stringList($raw, 'frontends', $ctx) as $name) {
                $frameworkFrontends[] = ($frontends[$name] ?? throw new MatrixException(\sprintf('%s : front « %s » non déclaré dans « frontends ».', $ctx, $name)))->frontend;
            }

            $frameworks[$key] = new FrameworkDefinition(
                $framework,
                $this->str($raw, 'label', $ctx),
                $versions,
                $frameworkDatabases,
                $frameworkFrontends,
                $this->bool($raw, 'custom_namespace', $ctx),
            );
        }

        if ([] === $frameworks) {
            throw new MatrixException('La matrice ne déclare aucun framework.');
        }

        $docker = $data['docker'] ?? null;
        if (!\is_array($docker)) {
            throw new MatrixException('Section « docker » manquante.');
        }
        $phpImage = $this->str($docker, 'php_image', 'docker');
        if (!str_contains($phpImage, '{php}')) {
            throw new MatrixException('docker.php_image doit contenir « {php} ».');
        }

        return new Matrix(
            $frameworks,
            $databases,
            $frontends,
            $services,
            new DockerImages($phpImage, $this->str($docker, 'nginx_image', 'docker'), $this->str($docker, 'node_image', 'docker')),
        );
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function map(mixed $data, string $key, string $ctx = 'matrix'): array
    {
        $value = \is_array($data) ? ($data[$key] ?? []) : null;
        if (!\is_array($value)) {
            throw new MatrixException(\sprintf('%s.%s doit être un objet.', $ctx, $key));
        }

        $result = [];
        foreach ($value as $k => $v) {
            if (!\is_array($v)) {
                throw new MatrixException(\sprintf('%s.%s.%s doit être un objet.', $ctx, $key, $k));
            }
            $result[(string) $k] = $v;
        }

        return $result;
    }

    /**
     * @param array<mixed> $data
     */
    private function str(array $data, string $key, string $ctx): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || '' === trim($value)) {
            throw new MatrixException(\sprintf('%s.%s : texte obligatoire.', $ctx, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function optionalStr(array $data, string $key, string $ctx): ?string
    {
        return \array_key_exists($key, $data) ? $this->str($data, $key, $ctx) : null;
    }

    /**
     * @param array<mixed> $data
     */
    private function bool(array $data, string $key, string $ctx): bool
    {
        $value = $data[$key] ?? null;
        if (!\is_bool($value)) {
            throw new MatrixException(\sprintf('%s.%s : true ou false attendu.', $ctx, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function int(array $data, string $key, string $ctx): int
    {
        $value = $data[$key] ?? null;
        if (!\is_int($value) || $value <= 0 || $value > 65535) {
            throw new MatrixException(\sprintf('%s.%s : port invalide.', $ctx, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function stringList(array $data, string $key, string $ctx): array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value) || [] === $value || !array_is_list($value)) {
            throw new MatrixException(\sprintf('%s.%s doit être une liste non vide.', $ctx, $key));
        }
        $result = [];
        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw new MatrixException(\sprintf('%s.%s ne doit contenir que du texte (pensez aux guillemets autour des versions).', $ctx, $key));
            }
            $result[] = $item;
        }
        if (\count(array_unique($result)) !== \count($result)) {
            throw new MatrixException(\sprintf('%s.%s contient des doublons.', $ctx, $key));
        }

        return $result;
    }
}
