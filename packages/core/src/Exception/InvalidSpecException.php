<?php

declare(strict_types=1);

namespace Gabarit\Core\Exception;

/**
 * Spec invalide (format ou combinaison absente de la matrice). Code de sortie CLI : 1.
 */
final class InvalidSpecException extends \InvalidArgumentException implements GabaritException
{
    /**
     * @param array<string, string> $violations champ => message en français
     */
    public function __construct(public readonly array $violations)
    {
        $lines = [];
        foreach ($violations as $field => $message) {
            $lines[] = \sprintf('%s : %s', $field, $message);
        }

        parent::__construct("Spécification invalide :\n- ".implode("\n- ", $lines));
    }
}
