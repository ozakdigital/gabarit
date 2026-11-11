<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Model;

use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ProjectSpecTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function valid(): array
    {
        return [
            'name' => 'mon-app',
            'framework' => 'symfony',
            'frameworkVersion' => '7.4',
            'phpVersion' => '8.3',
            'database' => 'postgresql',
            'frontend' => 'vue',
            'services' => ['redis'],
        ];
    }

    public function testFromArrayBuildsAnImmutableSpec(): void
    {
        $spec = ProjectSpec::fromArray(self::valid());

        self::assertSame('mon-app', $spec->name);
        self::assertSame(Database::PostgreSql, $spec->database);
        self::assertSame(Frontend::Vue, $spec->frontend);
        self::assertSame([Service::Redis], $spec->services);
        self::assertSame('Mon App', $spec->brand->displayName);
        self::assertSame('App', $spec->brand->phpNamespace);
        self::assertSame(self::valid() + ['brand' => $spec->brand->toArray()], $spec->toArray());
    }

    public function testServicesAreDeduplicatedAndSorted(): void
    {
        $spec = self::spec(services: [Service::Redis, Service::Mailpit, Service::Redis]);

        self::assertSame([Service::Mailpit, Service::Redis], $spec->services);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'trop court' => ['a'];
        yield 'trop long' => [str_repeat('a', 41)];
        yield 'majuscules' => ['MonApp'];
        yield 'espace' => ['mon app'];
        yield 'tiret initial' => ['-app'];
        yield 'tiret final' => ['app-'];
        yield 'double tiret' => ['mon--app'];
        yield 'injection shell' => ['app;rm'];
        yield 'chemin' => ['../app'];
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNamesAreRejected(string $name): void
    {
        $this->expectException(InvalidSpecException::class);
        ProjectSpec::fromArray(['name' => $name] + self::valid());
    }

    public function testNameBoundaries(): void
    {
        self::assertSame('ab', self::spec(name: 'ab')->name);
        self::assertSame(str_repeat('a', 40), self::spec(name: str_repeat('a', 40))->name);
    }

    public function testUnknownValuesAreAllReported(): void
    {
        try {
            ProjectSpec::fromArray(['framework' => 'rails', 'database' => 'oracle', 'frontend' => 'ember', 'services' => ['kafka']] + self::valid());
            self::fail('Exception attendue');
        } catch (InvalidSpecException $e) {
            self::assertSame(['framework', 'database', 'frontend', 'services'], array_keys($e->violations));
        }
    }

    public function testMissingFieldsAreReported(): void
    {
        try {
            ProjectSpec::fromArray([]);
            self::fail('Exception attendue');
        } catch (InvalidSpecException $e) {
            self::assertArrayHasKey('name', $e->violations);
            self::assertArrayHasKey('frameworkVersion', $e->violations);
            self::assertArrayHasKey('phpVersion', $e->violations);
        }
    }

    public function testNonStringValuesAreRejected(): void
    {
        $this->expectException(InvalidSpecException::class);
        ProjectSpec::fromArray(['phpVersion' => 8.3, 'services' => 'redis'] + self::valid());
    }

    public function testBrandFromArray(): void
    {
        $spec = ProjectSpec::fromArray(self::valid() + ['brand' => ['displayName' => 'Acme Shop', 'phpNamespace' => 'Acme\\Shop', 'primaryColor' => '#FF0000']]);

        self::assertSame('Acme Shop', $spec->brand->displayName);
        self::assertSame('Acme\\Shop', $spec->brand->phpNamespace);
        self::assertSame('#ff0000', $spec->brand->primaryColor);
    }

    public function testInvalidBrandIsReported(): void
    {
        try {
            ProjectSpec::fromArray(self::valid() + ['brand' => ['displayName' => '{{ evil }}']]);
            self::fail('Exception attendue');
        } catch (InvalidSpecException $e) {
            self::assertArrayHasKey('brand.displayName', $e->violations);
        }
    }
}
