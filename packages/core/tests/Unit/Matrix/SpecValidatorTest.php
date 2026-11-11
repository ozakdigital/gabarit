<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Matrix;

use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Matrix\SpecValidator;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Framework;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Tests\Support\TestCase;

final class SpecValidatorTest extends TestCase
{
    public function testEveryMatrixCombinationIsValid(): void
    {
        $validator = new SpecValidator(self::matrix());
        foreach (self::matrix()->combinations() as $c) {
            $spec = new ProjectSpec('app', $c['framework'], $c['frameworkVersion'], $c['phpVersion'], $c['database'], $c['frontend'], [Service::Redis, Service::Mailpit], Brand::fromProjectName('app'));
            self::assertSame([], $validator->validate($spec));
        }
    }

    public function testUnknownVersionIsRejected(): void
    {
        $violations = (new SpecValidator(self::matrix()))->validate(self::spec(version: '6.4'));

        self::assertArrayHasKey('frameworkVersion', $violations);
        self::assertStringContainsString('7.4', $violations['frameworkVersion']);
    }

    public function testIncompatiblePhpIsRejected(): void
    {
        $violations = (new SpecValidator(self::matrix()))->validate(self::spec(php: '8.1'));

        self::assertSame(['phpVersion'], array_keys($violations));
    }

    public function testCombinationAbsentFromMatrixIsRejected(): void
    {
        // Blade est un front Laravel, pas Symfony ; Twig est un front Symfony, pas Laravel ; MinIO n'est pas proposé.
        $validator = new SpecValidator(self::matrix());

        self::assertSame(['frontend', 'services'], array_keys($validator->validate(self::spec(frontend: Frontend::Blade, services: [Service::Minio]))));
        self::assertSame(['frontend'], array_keys($validator->validate(self::spec(frontend: Frontend::Twig, version: '13', framework: Framework::Laravel))));
    }

    public function testLaravelRequiresPhp83(): void
    {
        $violations = (new SpecValidator(self::matrix()))->validate(self::spec(php: '8.2', frontend: Frontend::Blade, version: '13', framework: Framework::Laravel));

        self::assertSame(['phpVersion'], array_keys($violations));
    }

    public function testCodeIgniterRefusesABrandNamespace(): void
    {
        $validator = new SpecValidator(self::matrix());
        $spec = static fn (string $ns): ProjectSpec => self::spec(frontend: Frontend::None, brand: new Brand('Acme', $ns), version: '4.7', framework: Framework::CodeIgniter);

        self::assertSame(['brand.phpNamespace'], array_keys($validator->validate($spec('Acme'))));
        self::assertSame([], $validator->validate($spec('App')));
    }

    public function testVersionOfAnotherFrameworkIsRejected(): void
    {
        $spec = new ProjectSpec('app', Framework::Laravel, '12', '8.3', Database::MySql, Frontend::Blade, [], Brand::fromProjectName('app'));

        $this->expectException(InvalidSpecException::class);
        (new SpecValidator(self::matrix()))->assertValid($spec);
    }
}
