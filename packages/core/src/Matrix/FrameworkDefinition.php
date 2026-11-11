<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\Frontend;

final readonly class FrameworkDefinition
{
    /**
     * @param array<string, FrameworkVersion> $versions
     * @param list<Database>                  $databases
     * @param list<Frontend>                  $frontends
     */
    public function __construct(
        public Framework $framework,
        public string $label,
        public array $versions,
        public array $databases,
        public array $frontends,
        /** Le namespace « App » peut-il être remplacé par celui de la marque ? */
        public bool $customNamespace = true,
    ) {
    }

    public function version(string $version): ?FrameworkVersion
    {
        return $this->versions[$version] ?? null;
    }
}
