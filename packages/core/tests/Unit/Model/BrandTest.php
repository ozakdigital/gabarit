<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Model;

use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Model\Brand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrandTest extends TestCase
{
    public function testDefaultsFromProjectName(): void
    {
        $brand = Brand::fromProjectName('mon-super-app');

        self::assertSame('Mon Super App', $brand->displayName);
        self::assertSame('App', $brand->phpNamespace);
        self::assertSame(Brand::DEFAULT_PRIMARY_COLOR, $brand->primaryColor);
    }

    public function testAccentsAndPunctuationAreAllowed(): void
    {
        $brand = new Brand("L'Atelier & Fils, Équipe-2", 'Atelier\\Web');

        self::assertSame("L'Atelier & Fils, Équipe-2", $brand->displayName);
    }

    public function testComposerVendorIsKebabCaseOfFirstSegment(): void
    {
        self::assertSame('acme-corp', (new Brand('X', 'AcmeCorp\\Shop'))->composerVendor());
        self::assertSame('app', (new Brand('X'))->composerVendor());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'nom avec accolades' => ['{{ x }}', 'App', '#000000'];
        yield 'nom avec dollar' => ['$HOME', 'App', '#000000'];
        yield 'nom avec guillemet double' => ['A"B', 'App', '#000000'];
        yield 'nom vide' => ['', 'App', '#000000'];
        yield 'namespace minuscule' => ['Ok', 'acme', '#000000'];
        yield 'namespace avec antislash final' => ['Ok', 'Acme\\', '#000000'];
        yield 'namespace réservé' => ['Ok', 'Acme\\Class', '#000000'];
        yield 'namespace trop profond' => ['Ok', 'A\\B\\C\\D\\E', '#000000'];
        yield 'couleur courte' => ['Ok', 'App', '#fff'];
        yield 'couleur CSS injectée' => ['Ok', 'App', 'red;}body{'];
    }

    #[DataProvider('invalid')]
    public function testInvalidValuesAreRejected(string $name, string $namespace, string $color): void
    {
        $this->expectException(InvalidSpecException::class);
        new Brand($name, $namespace, $color);
    }
}
