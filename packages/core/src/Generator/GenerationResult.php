<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator;

use Gabarit\Core\Model\ProjectSpec;

final readonly class GenerationResult
{
    /**
     * @param list<string> $steps    étapes exécutées, dans l'ordre
     * @param list<string> $warnings avertissements non bloquants
     */
    public function __construct(
        public ProjectSpec $spec,
        public string $targetDir,
        public array $steps,
        public array $warnings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'targetDir' => $this->targetDir,
            'spec' => $this->spec->toArray(),
            'steps' => $this->steps,
            'warnings' => $this->warnings,
        ];
    }
}
