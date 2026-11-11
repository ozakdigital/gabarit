<?php

declare(strict_types=1);

namespace Gabarit\Core\Framework;

use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Support\EnvFile;

/**
 * CodeIgniter 4 : .env (non versionné) créé depuis le modèle « env », clés « section.clé = valeur ».
 * Le namespace « App » est imposé (constante APP_NAMESPACE) : pas de namespace de marque.
 */
final class CodeIgniterProfile implements FrameworkProfile
{
    private const string HEALTH_MARKER = '// gabarit:health';
    private const string SEPARATOR = ' = ';

    public function postInstallCommands(ProjectSpec $spec): array
    {
        return [];
    }

    public function cleanup(GenerationContext $context): void
    {
    }

    public function configureDatabase(GenerationContext $context): void
    {
        $vars = [
            'CI_ENVIRONMENT' => 'development',
            'app.baseURL' => 'http://localhost:8080/',
            'database.default.DBDriver' => self::driver($context->spec->database),
        ];
        if (DatabaseSettings::isServer($context)) {
            $vars += [
                'database.default.hostname' => DatabaseSettings::HOST,
                'database.default.port' => DatabaseSettings::port($context),
                'database.default.database' => DatabaseSettings::NAME,
                'database.default.username' => DatabaseSettings::USER,
            ];
            // La config par défaut de CodeIgniter vise MySQL (utf8mb4), refusé par PostgreSQL.
            if (Database::PostgreSql === $context->spec->database) {
                $vars['database.default.charset'] = 'utf8';
            }
        } else {
            // Chemin relatif à writable/.
            $vars['database.default.database'] = 'database.db';
        }
        $this->setBlock($context, 'gabarit/app', $vars);

        $context->render('codeigniter/Health.php.twig', 'app/Controllers/Health.php');
        $routes = $context->read('app/Config/Routes.php');
        if (!str_contains($routes, self::HEALTH_MARKER)) {
            $context->write('app/Config/Routes.php', rtrim($routes)."\n\n".self::HEALTH_MARKER."\n\$routes->get('api/health', 'Health::index');\n");
        }
    }

    public function configureServices(GenerationContext $context): void
    {
        $vars = [];
        if ($context->spec->hasService(Service::Redis)) {
            $vars += ['cache.redis.host' => 'redis', 'cache.redis.port' => '6379'];
        }
        if ($context->spec->hasService(Service::Mailpit)) {
            $vars += ['email.protocol' => 'smtp', 'email.SMTPHost' => 'mailpit', 'email.SMTPPort' => '1025'];
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
        return [];
    }

    public function cacheDir(): ?string
    {
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
        // Clé conservée si elle existe déjà (idempotence).
        $existingKey = 1 === preg_match('/^encryption\.key = "?(hex2bin:[0-9a-f]{64})"?$/m', $context->readOrEmpty('.env'), $m) ? $m[1] : null;
        $vars = ['encryption.key' => $existingKey ?? 'hex2bin:'.bin2hex($secrets['APP_SECRET'])];
        if (DatabaseSettings::isServer($context)) {
            // DB_* : lus par docker compose ; database.default.password : lu par CodeIgniter.
            $vars['DB_PASSWORD'] = $secrets['DB_PASSWORD'];
            if (isset($secrets['DB_ROOT_PASSWORD'])) {
                $vars['DB_ROOT_PASSWORD'] = $secrets['DB_ROOT_PASSWORD'];
            }
            $vars['database.default.password'] = $secrets['DB_PASSWORD'];
        }
        $this->setBlock($context, 'gabarit/secrets', $vars);
    }

    public function phpExtensions(Database $database): array
    {
        // CodeIgniter utilise les pilotes natifs (MySQLi, Postgre, SQLite3), pas PDO.
        return match ($database) {
            Database::PostgreSql => ['pgsql'],
            Database::MySql, Database::MariaDb => ['mysqli'],
            Database::Sqlite => [],
        };
    }

    public function checkCommand(): string
    {
        return 'php spark routes';
    }

    public function postUpCommands(): array
    {
        return [];
    }

    public static function driver(Database $database): string
    {
        return match ($database) {
            Database::PostgreSql => 'Postgre',
            Database::MySql, Database::MariaDb => 'MySQLi',
            Database::Sqlite => 'SQLite3',
        };
    }

    /**
     * @param array<string, string> $vars
     */
    private function setBlock(GenerationContext $context, string $id, array $vars): void
    {
        $env = $context->exists('.env') ? $context->read('.env') : $context->readOrEmpty('env');
        $context->write('.env', EnvFile::setBlock($env, $id, $vars, self::SEPARATOR));
    }
}
