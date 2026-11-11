<?php

declare(strict_types=1);

namespace Gabarit\Core\Secret;

final class RandomSecretGenerator implements SecretGenerator
{
    private const string ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function generate(int $length): string
    {
        $max = \strlen(self::ALPHABET) - 1;
        $secret = '';
        for ($i = 0; $i < $length; ++$i) {
            $secret .= self::ALPHABET[random_int(0, $max)];
        }

        return $secret;
    }
}
