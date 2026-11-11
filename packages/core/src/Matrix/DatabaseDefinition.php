<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

use Gabarit\Core\Model\Database;

final readonly class DatabaseDefinition
{
    public function __construct(
        public Database $database,
        public string $label,
        /** false = base embarquée dans un fichier (SQLite), sans conteneur. */
        public bool $server,
        public ?string $image = null,
        public ?int $port = null,
        public ?string $serverVersion = null,
    ) {
    }
}
