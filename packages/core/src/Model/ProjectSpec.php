<?php

declare(strict_types=1);

namespace Gabarit\Core\Model;

use Gabarit\Core\Exception\InvalidSpecException;

/**
 * Description complète du projet à générer.
 *
 * Le constructeur vérifie le format des valeurs. La compatibilité entre elles
 * (version de PHP, base, front…) est vérifiée contre la matrice par SpecValidator.
 */
final readonly class ProjectSpec
{
    private const string NAME_PATTERN = '/^[a-z0-9][a-z0-9-]{0,38}[a-z0-9]$/';
    private const string VERSION_PATTERN = '/^\d+(\.\d+){0,2}$/';

    /** @var list<Service> triés par valeur pour une sortie déterministe */
    public array $services;

    /**
     * @param list<Service> $services
     */
    public function __construct(
        public string $name,
        public Framework $framework,
        public string $frameworkVersion,
        public string $phpVersion,
        public Database $database,
        public Frontend $frontend,
        array $services,
        public Brand $brand,
    ) {
        $violations = [];

        if (1 !== preg_match(self::NAME_PATTERN, $name) || str_contains($name, '--')) {
            $violations['name'] = 'Le nom du projet doit faire 2 à 40 caractères parmi [a-z0-9-], sans tiret au début ni à la fin.';
        }
        if (1 !== preg_match(self::VERSION_PATTERN, $frameworkVersion)) {
            $violations['frameworkVersion'] = 'Version de framework invalide.';
        }
        if (1 !== preg_match(self::VERSION_PATTERN, $phpVersion)) {
            $violations['phpVersion'] = 'Version de PHP invalide.';
        }

        $unique = [];
        foreach ($services as $service) {
            $unique[$service->value] = $service;
        }
        ksort($unique);
        $this->services = array_values($unique);

        if ([] !== $violations) {
            throw new InvalidSpecException($violations);
        }
    }

    /**
     * Construit une spec depuis des valeurs brutes (options CLI, JSON de l'API).
     * Toute valeur inconnue est refusée.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $violations = [];

        $name = self::string($data, 'name', $violations);
        $framework = self::enum(Framework::class, $data, 'framework', $violations);
        $frameworkVersion = self::string($data, 'frameworkVersion', $violations);
        $phpVersion = self::string($data, 'phpVersion', $violations);
        $database = self::enum(Database::class, $data, 'database', $violations);
        $frontend = self::enum(Frontend::class, $data, 'frontend', $violations);

        $services = [];
        $rawServices = $data['services'] ?? [];
        if (!\is_array($rawServices)) {
            $violations['services'] = 'Les services doivent être une liste.';
            $rawServices = [];
        }
        foreach ($rawServices as $raw) {
            $service = \is_string($raw) ? Service::tryFrom($raw) : null;
            if (null === $service) {
                $violations['services'] = \sprintf('Service inconnu : « %s ».', \is_scalar($raw) ? (string) $raw : \gettype($raw));
                continue;
            }
            $services[] = $service;
        }

        $brand = null;
        try {
            $brand = self::brand($data['brand'] ?? null, $name);
        } catch (InvalidSpecException $e) {
            $violations += $e->violations;
        }

        if ([] !== $violations || null === $framework || null === $database || null === $frontend || null === $brand) {
            // On tente quand même la construction pour remonter les erreurs de format en une fois.
            if (null !== $framework && null !== $database && null !== $frontend && null !== $brand) {
                try {
                    new self($name, $framework, $frameworkVersion, $phpVersion, $database, $frontend, $services, $brand);
                } catch (InvalidSpecException $e) {
                    $violations += $e->violations;
                }
            }

            throw new InvalidSpecException($violations);
        }

        return new self($name, $framework, $frameworkVersion, $phpVersion, $database, $frontend, $services, $brand);
    }

    /**
     * @return array{name: string, framework: string, frameworkVersion: string, phpVersion: string, database: string, frontend: string, services: list<string>, brand: array<string, string|null>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'framework' => $this->framework->value,
            'frameworkVersion' => $this->frameworkVersion,
            'phpVersion' => $this->phpVersion,
            'database' => $this->database->value,
            'frontend' => $this->frontend->value,
            'services' => array_map(static fn (Service $s): string => $s->value, $this->services),
            'brand' => $this->brand->toArray(),
        ];
    }

    public function hasService(Service $service): bool
    {
        return \in_array($service, $this->services, true);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $violations
     */
    private static function string(array $data, string $key, array &$violations): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || '' === $value) {
            $violations[$key] = \sprintf('Le champ « %s » est obligatoire.', $key);

            return '';
        }

        return $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T>       $enum
     * @param array<string, mixed>  $data
     * @param array<string, string> $violations
     *
     * @return T|null
     */
    private static function enum(string $enum, array $data, string $key, array &$violations): ?\BackedEnum
    {
        $value = $data[$key] ?? null;
        $case = \is_string($value) ? $enum::tryFrom($value) : null;
        if (null === $case) {
            $allowed = implode(', ', array_map(static fn (\BackedEnum $c): string => (string) $c->value, $enum::cases()));
            $violations[$key] = \sprintf('Valeur invalide pour « %s » (attendu : %s).', $key, $allowed);
        }

        return $case;
    }

    private static function brand(mixed $raw, string $projectName): Brand
    {
        if (null === $raw) {
            return Brand::fromProjectName('' !== $projectName ? $projectName : 'app');
        }
        if (!\is_array($raw)) {
            throw new InvalidSpecException(['brand' => 'La marque doit être un objet.']);
        }

        $get = static function (string $key, ?string $default) use ($raw): ?string {
            $value = $raw[$key] ?? $default;
            if (null !== $value && !\is_string($value)) {
                throw new InvalidSpecException(['brand.'.$key => 'Valeur texte attendue.']);
            }

            return $value;
        };

        $default = Brand::fromProjectName('' !== $projectName ? $projectName : 'app');

        return new Brand(
            (string) $get('displayName', $default->displayName),
            (string) $get('phpNamespace', $default->phpNamespace),
            (string) $get('primaryColor', Brand::DEFAULT_PRIMARY_COLOR),
            (string) $get('secondaryColor', Brand::DEFAULT_SECONDARY_COLOR),
            $get('logoPath', null),
        );
    }
}
