<?php

declare(strict_types=1);

namespace Gabarit\Core\Exception;

/**
 * Une commande externe (composer, npm, git…) a échoué.
 */
final class ProcessFailedException extends \RuntimeException implements GabaritException
{
    /**
     * @param list<string> $command
     */
    public function __construct(public readonly array $command, public readonly int $exitCode, public readonly string $errorOutput)
    {
        parent::__construct(\sprintf(
            "La commande « %s » a échoué (code %d).\n%s",
            implode(' ', $command),
            $exitCode,
            mb_substr(trim($errorOutput), -2000),
        ));
    }
}
