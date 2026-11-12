<?php

declare(strict_types=1);

namespace Gabarit\Core\Framework;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Support\EnvFile;

/**
 * Symfony : Doctrine (DATABASE_URL), .env versionné + .env.local pour les secrets, Twig.
 */
final class SymfonyProfile implements FrameworkProfile
{
    public const string PASSWORD_PLACEHOLDER = '!ChangeMe!';

    public function postInstallCommands(ProjectSpec $spec): array
    {
        $packages = ['symfony/orm-pack'];
        if (Frontend::Twig === $spec->frontend) {
            $packages[] = 'symfony/twig-bundle';
        }
        if ($spec->hasService(Service::Mailpit)) {
            $packages[] = 'symfony/mailer';
        }

        return [
            // Les fichiers Docker sont produits par Gabarit, pas par les recettes Flex.
            ['composer', 'config', 'extra.symfony.docker', 'false'],
            ['composer', 'require', ...$packages, '--no-interaction', '--no-progress'],
        ];
    }

    public function cleanup(GenerationContext $context): void
    {
    }

    public function configureDatabase(GenerationContext $context): void
    {
        $context->write('.env', EnvFile::setBlock($context->readOrEmpty('.env'), 'doctrine/doctrine-bundle', [
            'DATABASE_URL' => self::databaseUrl($context, self::PASSWORD_PLACEHOLDER),
        ]));
        $context->render('symfony/HealthController.php.twig', 'src/Controller/HealthController.php');
    }

    public function configureServices(GenerationContext $context): void
    {
        $env = $context->readOrEmpty('.env');
        foreach ($context->spec->services as $service) {
            $env = match ($service) {
                Service::Redis => EnvFile::setBlock($env, 'gabarit/redis', ['REDIS_URL' => 'redis://redis:6379']),
                Service::Mailpit => EnvFile::setBlock($env, 'symfony/mailer', ['MAILER_DSN' => 'smtp://mailpit:1025']),
                default => throw new GenerationException(\sprintf('Service non pris en charge : %s.', $service->value)),
            };
        }
        $context->write('.env', $env);
    }

    public function renderServerFrontend(GenerationContext $context): void
    {
        if (Frontend::Twig !== $context->spec->frontend) {
            return;
        }
        $context->render('symfony/base.html.twig.twig', 'templates/base.html.twig');
        $context->render('symfony/home.html.twig.twig', 'templates/home/index.html.twig');
        $context->render('symfony/HomeController.php.twig', 'src/Controller/HomeController.php');
    }

    public function namespacePaths(): array
    {
        // config/reference.php est généré par Symfony : jamais modifié.
        return ['src', 'tests', 'bin/console', 'public/index.php', 'config/services.yaml', 'config/packages/doctrine.yaml', 'config/routes.yaml'];
    }

    public function cacheDir(): string
    {
        return 'var/cache';
    }

    public function writablePaths(): array
    {
        return ['var'];
    }

    public function secretsFile(): string
    {
        return '.env.local';
    }

    public function containerEnvFile(): ?string
    {
        return null;
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
        $vars['DATABASE_URL'] = self::databaseUrl($context, $secrets['DB_PASSWORD']);

        $current = $context->readOrEmpty('.env.local');
        $header = "# Secrets locaux générés par Gabarit — NE PAS VERSIONNER.\n";
        $context->write('.env.local', EnvFile::setBlock('' === $current ? $header : $current, 'gabarit/secrets', $vars));
    }

    public function phpExtensions(Database $database): array
    {
        return DatabaseSettings::pdoExtension($database);
    }

    public function checkCommand(): string
    {
        return 'php bin/console lint:container';
    }

    public function postUpCommands(): array
    {
        return [];
    }

    public static function databaseUrl(GenerationContext $context, string $password): string
    {
        $db = DatabaseSettings::definition($context);
        $host = DatabaseSettings::HOST;

        return match ($context->spec->database) {
            Database::PostgreSql => \sprintf('postgresql://app:%s@%s:%d/app?serverVersion=%s&charset=utf8', $password, $host, $db->port, $db->serverVersion),
            Database::MySql, Database::MariaDb => \sprintf('mysql://app:%s@%s:%d/app?serverVersion=%s&charset=utf8mb4', $password, $host, $db->port, $db->serverVersion),
            Database::Sqlite => 'sqlite:///%kernel.project_dir%/var/data.db',
        };
    }
}
