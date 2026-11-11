<?php

declare(strict_types=1);

namespace Gabarit\Core\Generator\Step;

use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Generator\GeneratorStep;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Support\EnvFile;

/**
 * 9. Mots de passe aléatoires, écrits par le profil dans un fichier non versionné
 * (.env.local pour Symfony, .env ailleurs). Idempotent : un secret déjà présent est conservé.
 */
final class SecretsStep implements GeneratorStep
{
    public function name(): string
    {
        return 'secrets';
    }

    public function label(): string
    {
        return 'Génération des secrets';
    }

    public function run(GenerationContext $context): void
    {
        $profile = $context->profile();
        $existing = EnvFile::parse($context->readOrEmpty($profile->secretsFile()));

        $keep = static fn (string $key, int $length): string => isset($existing[$key]) && '' !== $existing[$key]
            ? $existing[$key]
            : $context->secrets->generate($length);

        $secrets = [
            'APP_SECRET' => $keep('APP_SECRET', 32),
            'DB_PASSWORD' => $keep('DB_PASSWORD', 24),
        ];
        if (\in_array($context->spec->database, [Database::MySql, Database::MariaDb], true)) {
            $secrets['DB_ROOT_PASSWORD'] = $keep('DB_ROOT_PASSWORD', 24);
        }

        // Pas de chmod 0600 : PHP-FPM tourne sous www-data dans le conteneur et doit pouvoir lire ce fichier.
        // Sa protection repose sur .gitignore (jamais versionné).
        $profile->writeSecrets($context, $secrets);
    }
}
