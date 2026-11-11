<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

final readonly class FrameworkVersion
{
    /**
     * @param list<string> $phpVersions
     */
    public function __construct(
        public string $version,
        public array $phpVersions,
        public string $package,
        public string $constraint,
    ) {
    }
}
