<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

use Gabarit\Core\Model\Service;

final readonly class ServiceDefinition
{
    public function __construct(
        public Service $service,
        public string $label,
        public string $image,
        public int $port,
    ) {
    }
}
