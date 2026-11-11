<?php

declare(strict_types=1);

namespace Gabarit\Core\Framework;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Matrix\DatabaseDefinition;
use Gabarit\Core\Model\Database;

/**
 * Paramètres de connexion communs à tous les profils.
 */
final class DatabaseSettings
{
    public const string HOST = 'database';
    public const string NAME = 'app';
    public const string USER = 'app';

    public static function definition(GenerationContext $context): DatabaseDefinition
    {
        return $context->matrix->database($context->spec->database)
            ?? throw new GenerationException('Base absente de la matrice.');
    }

    public static function port(GenerationContext $context): string
    {
        return (string) (self::definition($context)->port ?? '');
    }

    public static function isServer(GenerationContext $context): bool
    {
        return self::definition($context)->server;
    }

    /**
     * Extension PDO de la base (vide pour SQLite, déjà compilée dans les images PHP officielles).
     *
     * @return list<string>
     */
    public static function pdoExtension(Database $database): array
    {
        return match ($database) {
            Database::PostgreSql => ['pdo_pgsql'],
            Database::MySql, Database::MariaDb => ['pdo_mysql'],
            Database::Sqlite => [],
        };
    }
}
