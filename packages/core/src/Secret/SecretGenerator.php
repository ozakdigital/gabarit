<?php

declare(strict_types=1);

namespace Gabarit\Core\Secret;

interface SecretGenerator
{
    /**
     * Chaîne aléatoire alphanumérique (sûre dans une URL, un .env ou un YAML).
     */
    public function generate(int $length): string;
}
