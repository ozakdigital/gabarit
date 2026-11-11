<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator;

final readonly class GenerationOptions
{
    public function __construct(
        /** git init + premier commit (désactivé côté web). */
        public bool $gitInit = true,
    ) {
    }
}
