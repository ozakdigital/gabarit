<?php

declare(strict_types=1);

namespace Gabarit\Core\Framework;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Support\EnvFile;

/**
 * Slim 4 (squelette officiel) : pas d'ORM ni de chargeur .env. Gabarit écrit un .env non versionné,
 * injecté dans le conteneur PHP par docker compose, et une route /api/health en PDO.
 */
final class SlimProfile implements FrameworkProfile
{
    private const string HEALTH_HOOK = "(require __DIR__ . '/../app/health.php')(\$app);";

    /** Le squelette 4.5.0 épingle php-di ^6.4, qui émet des avertissements de dépréciation sous PHP 8.4. */
    public const string PHP_DI_CONSTRAINT = 'php-di/php-di:^7.1';

    public function postInstallCommands(ProjectSpec $spec): array
    {
        return [
            ['composer', 'require', self::PHP_DI_CONSTRAINT, '--with-all-dependencies', '--no-interaction', '--no-progress'],
        ];
    }

    public function cleanup(GenerationContext $context): void
    {
        // Le squelette fournit son propre docker-compose.yml : il entrerait en conflit avec compose.yaml.
        if ($context->exists('docker-compose.yml')) {
            unlink($context->path('docker-compose.yml'));
        }
    }

    public function configureDatabase(GenerationContext $context): void
    {
        $vars = ['DB_DRIVER' => self::pdoDriver($context->spec->database)];
        if (DatabaseSettings::isServer($context)) {
            $vars += [
                'DB_HOST' => DatabaseSettings::HOST,
                'DB_PORT' => DatabaseSettings::port($context),
                'DB_NAME' => DatabaseSettings::NAME,
                'DB_USER' => DatabaseSettings::USER,
            ];
        } else {
            $vars['DB_PATH'] = '/var/www/app/var/database.sqlite';
        }
        $this->setBlock($context, 'gabarit/database', $vars);

        $context->render('slim/health.php.twig', 'app/health.php');

        // Branche la route juste après l'enregistrement des routes du squelette.
        $index = $context->read('public/index.php');
        if (!str_contains($index, self::HEALTH_HOOK)) {
            $count = 0;
            $index = preg_replace('/^(\$routes\(\$app\);)$/m', '$1'."\n".self::HEALTH_HOOK, $index, 1, $count) ?? $index;
            if (0 === $count) {
                throw new GenerationException('public/index.php : ligne « $routes($app); » introuvable, squelette Slim inattendu.');
            }
            $context->write('public/index.php', $index);
        }
    }

    public function configureServices(GenerationContext $context): void
    {
        $vars = [];
        if ($context->spec->hasService(Service::Redis)) {
            $vars += ['REDIS_HOST' => 'redis', 'REDIS_PORT' => '6379'];
        }
        if ($context->spec->hasService(Service::Mailpit)) {
            $vars['MAILER_DSN'] = 'smtp://mailpit:1025';
        }
        if ([] !== $vars) {
            $this->setBlock($context, 'gabarit/services', $vars);
        }
    }

    public function renderServerFrontend(GenerationContext $context): void
    {
    }

    public function namespacePaths(): array
    {
        return ['src', 'app', 'public/index.php', 'tests'];
    }

    public function cacheDir(): ?string
    {
        return null;
    }

    public function secretsFile(): string
    {
        return '.env';
    }

    public function containerEnvFile(): string
    {
        return '.env';
    }

    public function writeSecrets(GenerationContext $context, array $secrets): void
    {
        $vars = ['APP_SECRET' => $secrets['APP_SECRET']];
        if (DatabaseSettings::isServer($context)) {
            $vars['DB_PASSWORD'] = $secrets['DB_PASSWORD'];
            if (isset($secrets['DB_ROOT_PASSWORD'])) {
                $vars['DB_ROOT_PASSWORD'] = $secrets['DB_ROOT_PASSWORD'];
            }
        }
        $this->setBlock($context, 'gabarit/secrets', $vars);
    }

    public function phpExtensions(Database $database): array
    {
        return DatabaseSettings::pdoExtension($database);
    }

    public function checkCommand(): string
    {
        return 'php -l public/index.php';
    }

    public function postUpCommands(): array
    {
        return [];
    }

    public static function pdoDriver(Database $database): string
    {
        return match ($database) {
            Database::PostgreSql => 'pgsql',
            Database::MySql, Database::MariaDb => 'mysql',
            Database::Sqlite => 'sqlite',
        };
    }

    /**
     * @param array<string, string> $vars
     */
    private function setBlock(GenerationContext $context, string $id, array $vars): void
    {
        $current = $context->readOrEmpty('.env');
        $header = "# Environnement local généré par Gabarit — NE PAS VERSIONNER (contient des secrets).\n";
        $context->write('.env', EnvFile::setBlock('' === $current ? $header : $current, $id, $vars));
    }
}
