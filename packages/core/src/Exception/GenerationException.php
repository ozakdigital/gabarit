<?php

declare(strict_types=1);

namespace Gabarit\Core\Exception;

/**
 * Échec pendant l'exécution d'une étape de génération. Code de sortie CLI : 2.
 */
final class GenerationException extends \RuntimeException implements GabaritException
{
    public function __construct(string $message, public readonly ?string $step = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function inStep(string $step, \Throwable $previous): self
    {
        return new self(\sprintf('Échec de l\'étape « %s » : %s', $step, $previous->getMessage()), $step, $previous);
    }
}
