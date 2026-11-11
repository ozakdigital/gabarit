<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Model\ProjectSpec;

/**
 * Vérifie qu'une spec correspond à une combinaison déclarée dans la matrice.
 * Une combinaison absente est refusée, jamais « tentée ».
 */
final readonly class SpecValidator
{
    public function __construct(private Matrix $matrix)
    {
    }

    /**
     * @return array<string, string> violations (vide si la spec est valide)
     */
    public function validate(ProjectSpec $spec): array
    {
        $violations = [];

        $framework = $this->matrix->framework($spec->framework);
        if (null === $framework) {
            return ['framework' => \sprintf('Le framework « %s » n\'est pas disponible.', $spec->framework->value)];
        }

        $version = $framework->version($spec->frameworkVersion);
        if (null === $version) {
            $violations['frameworkVersion'] = \sprintf(
                'Version « %s » indisponible pour %s (disponibles : %s).',
                $spec->frameworkVersion,
                $framework->label,
                implode(', ', array_keys($framework->versions)),
            );
        } elseif (!\in_array($spec->phpVersion, $version->phpVersions, true)) {
            $violations['phpVersion'] = \sprintf(
                'PHP %s n\'est pas compatible avec %s %s (compatibles : %s).',
                $spec->phpVersion,
                $framework->label,
                $version->version,
                implode(', ', $version->phpVersions),
            );
        }

        if (!\in_array($spec->database, $framework->databases, true)) {
            $violations['database'] = \sprintf('La base « %s » n\'est pas disponible avec %s.', $spec->database->value, $framework->label);
        }

        if (!\in_array($spec->frontend, $framework->frontends, true)) {
            $violations['frontend'] = \sprintf('Le front « %s » n\'est pas disponible avec %s.', $spec->frontend->value, $framework->label);
        }

        if (!$framework->customNamespace && 'App' !== $spec->brand->phpNamespace) {
            $violations['brand.phpNamespace'] = \sprintf('%s impose le namespace « App » : un namespace de marque n\'est pas pris en charge.', $framework->label);
        }

        foreach ($spec->services as $service) {
            if (null === $this->matrix->service($service)) {
                $violations['services'] = \sprintf('Le service « %s » n\'est pas disponible.', $service->value);
            }
        }

        return $violations;
    }

    public function assertValid(ProjectSpec $spec): void
    {
        $violations = $this->validate($spec);
        if ([] !== $violations) {
            throw new InvalidSpecException($violations);
        }
    }
}
