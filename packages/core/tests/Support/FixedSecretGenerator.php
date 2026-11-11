<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Support;

use Gabarit\Core\Secret\SecretGenerator;

/**
 * Secrets prévisibles pour des tests déterministes.
 */
final class FixedSecretGenerator implements SecretGenerator
{
    private int $counter = 0;

    public function generate(int $length): string
    {
        ++$this->counter;

        return substr(str_repeat('secret'.$this->counter, 10), 0, $length);
    }
}
