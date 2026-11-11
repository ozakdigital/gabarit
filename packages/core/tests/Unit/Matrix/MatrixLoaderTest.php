<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Matrix;

use Gabarit\Core\Exception\MatrixException;
use Gabarit\Core\Matrix\FrontendKind;
use Gabarit\Core\Matrix\MatrixLoader;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MatrixLoaderTest extends TestCase
{
    public function testTheRepositoryMatrixLoads(): void
    {
        $matrix = self::matrix();

        $symfony = $matrix->framework(Framework::Symfony);
        self::assertNotNull($symfony);
        self::assertSame(['8.2', '8.3', '8.4'], $symfony->version('7.4')?->phpVersions);
        self::assertSame([Database::PostgreSql, Database::MySql, Database::MariaDb, Database::Sqlite], $symfony->databases);
        self::assertSame([Database::PostgreSql, Database::MySql, Database::MariaDb, Database::Sqlite], $matrix->framework(Framework::Laravel)?->databases);
        self::assertSame([Frontend::Blade, Frontend::None, Frontend::Vue, Frontend::React, Frontend::Svelte, Frontend::Angular, Frontend::Solid, Frontend::Preact], $matrix->framework(Framework::Laravel)->frontends);
        self::assertFalse($matrix->framework(Framework::CodeIgniter)?->customNamespace);
        self::assertFalse($matrix->database(Database::Sqlite)?->server);
        self::assertSame('mariadb:11.8', $matrix->database(Database::MariaDb)?->image);
        self::assertSame([Frontend::Twig, Frontend::None, Frontend::Vue, Frontend::React, Frontend::Svelte, Frontend::Angular, Frontend::Solid, Frontend::Preact], $symfony->frontends);
        self::assertSame(FrontendKind::Server, $matrix->frontend(Frontend::Twig)?->kind);
        self::assertSame(FrontendKind::Vite, $matrix->frontend(Frontend::React)?->kind);
        self::assertSame('svelte-ts', $matrix->frontend(Frontend::Svelte)?->template);
        self::assertSame(FrontendKind::Angular, $matrix->frontend(Frontend::Angular)?->kind);
        self::assertNotNull($matrix->service(Service::Redis));
        self::assertSame('php:8.3-fpm-alpine', $matrix->docker->php('8.3'));
    }

    public function testCombinationsCoverEveryEntry(): void
    {
        // Symfony 3×4×8 + Laravel 2×4×8 + CodeIgniter 3×4×7 + Slim 3×4×7 (PHP × bases × fronts)
        self::assertCount(96 + 64 + 84 + 84, self::matrix()->combinations());
    }

    public function testPublicArrayHasNoInternalDetails(): void
    {
        $array = self::matrix()->toArray();

        self::assertSame(['8.2', '8.3', '8.4'], $array['frameworks']['symfony']['versions']['7.4']['php']);
        self::assertArrayNotHasKey('image', $array['services']['redis']);
    }

    public function testMissingFileIsAnError(): void
    {
        $this->expectException(MatrixException::class);
        (new MatrixLoader())->load('/nope/matrix.yaml');
    }

    public function testInvalidYamlIsAnError(): void
    {
        $file = $this->tempDir().'/matrix.yaml';
        file_put_contents($file, "frameworks: [\n");

        $this->expectException(MatrixException::class);
        (new MatrixLoader())->load($file);
    }

    /**
     * @return iterable<string, array{0: callable(array<string, mixed>): array<string, mixed>, 1: string}>
     */
    public static function brokenMatrices(): iterable
    {
        yield 'framework inconnu' => [static function (array $m): array {
            $m['frameworks']['django'] = $m['frameworks']['symfony'];

            return $m;
        }, 'Framework inconnu'];
        yield 'base non déclarée' => [static function (array $m): array {
            unset($m['databases']['mariadb']);

            return $m;
        }, 'non déclarée'];
        yield 'front non déclaré' => [static function (array $m): array {
            unset($m['frontends']['blade']);

            return $m;
        }, 'non déclaré'];
        yield 'version PHP non textuelle' => [static function (array $m): array {
            $m['frameworks']['symfony']['versions']['7.4']['php'] = [8.3];

            return $m;
        }, 'guillemets'];
        yield 'doublon' => [static function (array $m): array {
            $m['frameworks']['symfony']['versions']['7.4']['php'] = ['8.3', '8.3'];

            return $m;
        }, 'doublons'];
        yield 'image manquante' => [static function (array $m): array {
            unset($m['services']['redis']['image']);

            return $m;
        }, 'services.redis.image'];
        yield 'aucun framework' => [static function (array $m): array {
            $m['frameworks'] = [];

            return $m;
        }, 'aucun framework'];
        yield 'kind de front inconnu' => [static function (array $m): array {
            $m['frontends']['react']['kind'] = 'webpack';

            return $m;
        }, 'frontends.react.kind'];
        yield 'installeur SPA sans version exacte' => [static function (array $m): array {
            $m['frontends']['angular']['installer'] = '@angular/cli@latest';

            return $m;
        }, 'version exacte'];
        yield 'modèle create-vite manquant' => [static function (array $m): array {
            unset($m['frontends']['svelte']['template']);

            return $m;
        }, 'frontends.svelte.template'];
        yield 'image PHP sans version' => [static function (array $m): array {
            $m['docker']['php_image'] = 'php:latest';

            return $m;
        }, '{php}'];
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mutate
     */
    #[DataProvider('brokenMatrices')]
    public function testIncoherentMatrixIsRejected(callable $mutate, string $message): void
    {
        $data = \Symfony\Component\Yaml\Yaml::parseFile(MatrixLoader::defaultPath());
        self::assertIsArray($data);

        $this->expectException(MatrixException::class);
        $this->expectExceptionMessage($message);
        /** @var array<string, mixed> $data */
        (new MatrixLoader())->fromArray($mutate($data));
    }
}
