<?php

declare(strict_types=1);

namespace Gabarit\Core\Framework;

use Gabarit\Core\Generator\GenerationContext;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\ProjectSpec;

/**
 * Tout ce qui diffère d'un framework PHP à l'autre. Les étapes du pipeline restent génériques
 * et délèguent ici ; un nouveau framework = une entrée de matrice + un profil + ses templates.
 *
 * Conventions communes à tous les profils : hôte de base « database », base « app »,
 * utilisateur « app », test de santé sur GET /api/health.
 */
interface FrameworkProfile
{
    /**
     * Commandes composer à lancer après create-project (tableaux d'arguments).
     *
     * @return list<list<string>>
     */
    public function postInstallCommands(ProjectSpec $spec): array;

    /** Retire du squelette ce qui entre en conflit avec les fichiers de Gabarit. */
    public function cleanup(GenerationContext $context): void;

    /** Configuration non secrète de la base + test de santé /api/health. */
    public function configureDatabase(GenerationContext $context): void;

    /** Variables d'environnement des services annexes (Redis, Mailpit). */
    public function configureServices(GenerationContext $context): void;

    /** Page d'accueil des fronts rendus côté serveur (Twig, Blade) ; rien pour « none ». */
    public function renderServerFrontend(GenerationContext $context): void;

    /**
     * Dossiers et fichiers où remplacer le namespace « App\ » (si la matrice l'autorise).
     *
     * @return list<string>
     */
    public function namespacePaths(): array;

    /** Dossiers de cache à vider après un changement de namespace. */
    public function cacheDir(): ?string;

    /**
     * Dossiers où le framework écrit à l'exécution (cache, logs, sessions, base SQLite).
     * Rendus inscriptibles par PHP-FPM (www-data) au démarrage du conteneur.
     *
     * @return list<string>
     */
    public function writablePaths(): array;

    /** Fichier non versionné qui porte les secrets ; docker compose le lit (--env-file). */
    public function secretsFile(): string;

    /**
     * Fichier d'environnement à injecter dans le conteneur PHP (frameworks sans chargeur .env).
     */
    public function containerEnvFile(): ?string;

    /**
     * Écrit les secrets générés. Clés fournies : APP_SECRET, DB_PASSWORD, DB_ROOT_PASSWORD (MySQL/MariaDB).
     *
     * @param array<string, string> $secrets
     */
    public function writeSecrets(GenerationContext $context, array $secrets): void;

    /**
     * Extensions PHP à compiler dans l'image (en plus de intl, zip, opcache).
     *
     * @return list<string>
     */
    public function phpExtensions(Database $database): array;

    /** Commande qui vérifie que l'application démarre (Makefile `test`, CI). */
    public function checkCommand(): string;

    /**
     * Commandes à lancer dans le conteneur après `make up` (migrations…).
     *
     * @return list<string>
     */
    public function postUpCommands(): array;
}
