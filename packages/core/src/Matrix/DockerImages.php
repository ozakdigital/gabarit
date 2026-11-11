<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

final readonly class DockerImages
{
    public function __construct(
        private string $phpImagePattern,
        public string $nginx,
        public string $node,
    ) {
    }

    public function php(string $phpVersion): string
    {
        return str_replace('{php}', $phpVersion, $this->phpImagePattern);
    }

    public function phpImagePattern(): string
    {
        return $this->phpImagePattern;
    }
}
