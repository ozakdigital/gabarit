<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\Service;

/**
 * Matrice de compatibilité chargée depuis config/matrix.yaml. Immuable.
 */
final readonly class Matrix
{
    /**
     * @param array<string, FrameworkDefinition> $frameworks clés = Framework::value
     * @param array<string, DatabaseDefinition>  $databases
     * @param array<string, FrontendDefinition>  $frontends
     * @param array<string, ServiceDefinition>   $services
     */
    public function __construct(
        public array $frameworks,
        public array $databases,
        public array $frontends,
        public array $services,
        public DockerImages $docker,
    ) {
    }

    public function framework(Framework $framework): ?FrameworkDefinition
    {
        return $this->frameworks[$framework->value] ?? null;
    }

    public function database(Database $database): ?DatabaseDefinition
    {
        return $this->databases[$database->value] ?? null;
    }

    public function frontend(Frontend $frontend): ?FrontendDefinition
    {
        return $this->frontends[$frontend->value] ?? null;
    }

    public function service(Service $service): ?ServiceDefinition
    {
        return $this->services[$service->value] ?? null;
    }

    /**
     * Toutes les combinaisons framework × version × PHP × base × front (les services sont orthogonaux).
     *
     * @return list<array{framework: Framework, frameworkVersion: string, phpVersion: string, database: Database, frontend: Frontend}>
     */
    public function combinations(): array
    {
        $combinations = [];
        foreach ($this->frameworks as $definition) {
            foreach ($definition->versions as $version) {
                foreach ($version->phpVersions as $php) {
                    foreach ($definition->databases as $database) {
                        foreach ($definition->frontends as $frontend) {
                            $combinations[] = [
                                'framework' => $definition->framework,
                                'frameworkVersion' => $version->version,
                                'phpVersion' => $php,
                                'database' => $database,
                                'frontend' => $frontend,
                            ];
                        }
                    }
                }
            }
        }

        return $combinations;
    }

    /**
     * Représentation publique (GET /api/matrix, `gabarit matrix --json`).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $frameworks = [];
        foreach ($this->frameworks as $key => $definition) {
            $versions = [];
            foreach ($definition->versions as $version) {
                $versions[$version->version] = ['php' => $version->phpVersions];
            }
            $frameworks[$key] = [
                'label' => $definition->label,
                'versions' => $versions,
                'databases' => array_map(static fn (Database $d): string => $d->value, $definition->databases),
                'frontends' => array_map(static fn (Frontend $f): string => $f->value, $definition->frontends),
                'customNamespace' => $definition->customNamespace,
            ];
        }

        return [
            'frameworks' => $frameworks,
            'databases' => array_map(static fn (DatabaseDefinition $d): array => ['label' => $d->label, 'server' => $d->server], $this->databases),
            'frontends' => array_map(static fn (FrontendDefinition $f): array => ['label' => $f->label, 'spa' => $f->kind->isSpa()], $this->frontends),
            'services' => array_map(static fn (ServiceDefinition $s): array => ['label' => $s->label], $this->services),
        ];
    }
}
