<?php

declare(strict_types=1);

namespace Gabarit\Core\Framework;

use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Support\EnvFile;

/**
 * Laravel : Eloquent, .env non versionné (secrets compris) + .env.example versionné, Blade.
 * Le create-project officiel génère déjà APP_KEY et migre la base SQLite par défaut.
 */
final class LaravelProfile implements FrameworkProfile
{
    private const string HEALTH_MARKER = '// gabarit:health';

    public function postInstallCommands(ProjectSpec $spec): array
    {
        return [];
    }

    public function cleanup(GenerationContext $context): void
    {
    }

    public function configureDatabase(GenerationContext $context): void
    {
        $vars = ['DB_CONNECTION' => self::connection($context->spec->database)];
        if (DatabaseSettings::isServer($context)) {
            $vars += [
                'DB_HOST' => DatabaseSettings::HOST,
                'DB_PORT' => DatabaseSettings::port($context),
                'DB_DATABASE' => DatabaseSettings::NAME,
                'DB_USERNAME' => DatabaseSettings::USER,
            ];
        }
        $vars += [
            'APP_NAME' => $context->spec->brand->displayName,
            'APP_URL' => 'http://localhost:8080',
        ];

        $this->setEnv($context, $vars);

        $context->render('laravel/HealthController.php.twig', 'app/Http/Controllers/HealthController.php');
        $routes = $context->read('routes/web.php');
        if (!str_contains($routes, self::HEALTH_MARKER)) {
            $routes = rtrim($routes)."\n\n".self::HEALTH_MARKER."\nRoute::get('/api/health', App\\Http\\Controllers\\HealthController::class);\n";
            $context->write('routes/web.php', $routes);
        }
    }

    public function configureServices(GenerationContext $context): void
    {
        $vars = [];
        if ($context->spec->hasService(Service::Redis)) {
            $vars += ['REDIS_HOST' => 'redis', 'REDIS_PORT' => '6379'];
        }
        if ($context->spec->hasService(Service::Mailpit)) {
            $vars += ['MAIL_MAILER' => 'smtp', 'MAIL_HOST' => 'mailpit', 'MAIL_PORT' => '1025'];
        }
        if ([] !== $vars) {
            $this->setEnv($context, $vars);
        }
    }

    public function renderServerFrontend(GenerationContext $context): void
    {
        if (Frontend::Blade === $context->spec->frontend) {
            $context->render('laravel/welcome.blade.php.twig', 'resources/views/welcome.blade.php');
        }
    }

    public function namespacePaths(): array
    {
        return ['app', 'bootstrap', 'config', 'database', 'routes', 'tests'];
    }

    public function cacheDir(): ?string
    {
        // bootstrap/cache doit exister ; Composer le régénère (package:discover) après dump-autoload.
        return null;
    }

    public function secretsFile(): string
    {
        return '.env';
    }

    public function containerEnvFile(): ?string
    {
        return null;
    }

    public function writeSecrets(GenerationContext $context, array $secrets): void
    {
        $this->ensureEnv($context);
        $current = EnvFile::parse($context->read('.env'));

        $vars = [];
        if ('' === ($current['APP_KEY'] ?? '')) {
            // 32 octets encodés en base64, format attendu par Laravel.
            $vars['APP_KEY'] = 'base64:'.base64_encode($secrets['APP_SECRET']);
        }
        if (DatabaseSettings::isServer($context)) {
            $vars['DB_PASSWORD'] = $secrets['DB_PASSWORD'];
            if (isset($secrets['DB_ROOT_PASSWORD'])) {
                $vars['DB_ROOT_PASSWORD'] = $secrets['DB_ROOT_PASSWORD'];
            }
        }
        $context->write('.env', EnvFile::set($context->read('.env'), $vars));
    }

    public function phpExtensions(Database $database): array
    {
        return DatabaseSettings::pdoExtension($database);
    }

    public function checkCommand(): string
    {
        return 'php artisan about --only=environment';
    }

    public function postUpCommands(): array
    {
        return ['php artisan migrate --force'];
    }

    public static function connection(Database $database): string
    {
        return match ($database) {
            Database::PostgreSql => 'pgsql',
            Database::MySql => 'mysql',
            Database::MariaDb => 'mariadb',
            Database::Sqlite => 'sqlite',
        };
    }

    /**
     * Même configuration non secrète dans .env (local) et .env.example (versionné).
     *
     * @param array<string, string> $vars
     */
    private function setEnv(GenerationContext $context, array $vars): void
    {
        $this->ensureEnv($context);
        foreach (['.env', '.env.example'] as $file) {
            if ($context->exists($file)) {
                $context->write($file, EnvFile::set($context->read($file), $vars));
            }
        }
    }

    private function ensureEnv(GenerationContext $context): void
    {
        if (!$context->exists('.env') && $context->exists('.env.example')) {
            $context->write('.env', $context->read('.env.example'));
        }
    }
}
