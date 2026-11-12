<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Template;

use Gabarit\Core\Frontend\SpaLayouts;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\Database;
use Gabarit\Core\Model\Frontend;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Model\Service;
use Gabarit\Core\Template\TemplateRenderer;
use Gabarit\Core\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;

/**
 * Chaque template doit se rendre sans erreur pour des specs variées (strict_variables activé).
 */
final class TemplateRenderingTest extends TestCase
{
    /**
     * @return iterable<string, array{ProjectSpec}>
     */
    public static function specs(): iterable
    {
        yield 'postgres + twig, sans service' => [self::spec()];
        yield 'mysql + vue + services' => [self::spec(database: Database::MySql, frontend: Frontend::Vue, services: [Service::Redis, Service::Mailpit])];
        yield 'react' => [self::spec(frontend: Frontend::React)];
        yield 'svelte' => [self::spec(frontend: Frontend::Svelte)];
        yield 'solid' => [self::spec(frontend: Frontend::Solid)];
        yield 'preact' => [self::spec(frontend: Frontend::Preact)];
        yield 'angular + logo' => [self::spec(frontend: Frontend::Angular, brand: new Brand("L'Atelier", logoPath: '/tmp/logo.png'))];
        yield 'marque complète' => [self::spec(php: '8.4', brand: new Brand("L'Atelier & Co", 'Atelier\\Web', '#112233', '#445566', '/tmp/logo.svg'))];
    }

    #[DataProvider('specs')]
    public function testEveryTemplateRenders(ProjectSpec $spec): void
    {
        $context = $this->context($spec)->templateContext();
        $renderer = new TemplateRenderer();

        $templates = TemplateRenderer::all();
        self::assertNotEmpty($templates);
        foreach ($templates as $template) {
            if (str_starts_with($template, 'services/') && !isset($context['services'][basename($template, '.yaml.twig')])) {
                continue; // fragment rendu seulement si le service est choisi
            }
            if (str_starts_with($template, 'frontend/') && !\array_key_exists($template, SpaLayouts::for($spec->frontend)->renders ?? [])) {
                continue; // composants d'un autre front
            }
            if (str_starts_with($template, 'starter/')) {
                continue; // contexte propre au starter : couvert par StarterTest
            }
            $output = $renderer->render($template, $context);
            self::assertNotSame('', trim($output), $template);
            self::assertStringNotContainsString('@@TAB@@', $output, $template);
        }
    }

    #[DataProvider('specs')]
    public function testComposeAndCiAreValidYaml(ProjectSpec $spec): void
    {
        $context = $this->context($spec)->templateContext();
        $renderer = new TemplateRenderer();

        $compose = Yaml::parse($renderer->render('docker/compose.yaml.twig', $context));
        self::assertIsArray($compose);
        self::assertSame($spec->name, $compose['name']);
        $spa = SpaLayouts::for($spec->frontend);
        self::assertSame(['app', 'nginx', 'database', ...(null !== $spa ? ['node'] : [])], array_keys($compose['services']));
        if (null !== $spa) {
            self::assertSame($spa->devCommand, $compose['services']['node']['command']);
            self::assertContains('frontend-node-modules:/app/node_modules', $compose['services']['node']['volumes']);
            self::assertArrayHasKey('frontend-node-modules', $compose['volumes']);
        }

        $ci = Yaml::parse($renderer->render('ci/github-actions.yml.twig', $context));
        self::assertIsArray($ci);
        self::assertSame($spec->phpVersion, $ci['jobs']['php']['steps'][1]['with']['php-version']);
    }

    public function testEveryFrontendTemplateBelongsToALayout(): void
    {
        $used = [];
        foreach (Frontend::cases() as $frontend) {
            $used += SpaLayouts::for($frontend)->renders ?? [];
        }

        $frontendTemplates = array_values(array_filter(TemplateRenderer::all(), static fn (string $t): bool => str_starts_with($t, 'frontend/')));
        self::assertSame([], array_values(array_diff($frontendTemplates, array_keys($used))), 'Template orphelin');
        self::assertSame([], array_values(array_diff(array_keys($used), $frontendTemplates)), 'Template manquant');
    }

    public function testDockerfileUsesTheChosenPhpImageAndExtensions(): void
    {
        $renderer = new TemplateRenderer();

        $pg = $renderer->render('docker/Dockerfile.twig', $this->context(self::spec(php: '8.2'))->templateContext());
        self::assertStringContainsString('FROM php:8.2-fpm-alpine', $pg);
        self::assertStringContainsString('pdo_pgsql', $pg);
        self::assertStringContainsString('postgresql-dev', $pg);
        self::assertStringNotContainsString('pecl install redis', $pg);

        $my = $renderer->render('docker/Dockerfile.twig', $this->context(self::spec(database: Database::MySql, services: [Service::Redis]))->templateContext());
        self::assertStringContainsString('pdo_mysql', $my);
        self::assertStringNotContainsString('postgresql-dev', $my);
        self::assertStringContainsString('pecl install redis', $my);
    }

    public function testMakefileRecipesUseTabs(): void
    {
        $makefile = (new TemplateRenderer())->render('common/Makefile.twig', $this->context(self::spec())->templateContext());

        self::assertMatchesRegularExpression('/^up:.*\n\t\$\(COMPOSE\) up -d --build$/m', $makefile);
        self::assertDoesNotMatchRegularExpression('/^ {4}\$\(COMPOSE\)/m', $makefile);
    }

    public function testNoSecretIsHardCodedInTemplates(): void
    {
        foreach (TemplateRenderer::all() as $template) {
            $source = (string) file_get_contents(TemplateRenderer::defaultDir().'/'.$template);
            self::assertDoesNotMatchRegularExpression('/(PASSWORD|SECRET)\s*[:=]\s*["\']?[A-Za-z0-9]{6,}/', $source, $template);
        }
    }

    public function testBrandValuesAreEscapedForHtml(): void
    {
        $spec = self::spec(brand: new Brand("L'Atelier & Co"));
        $home = (new TemplateRenderer())->render('symfony/home.html.twig.twig', $this->context($spec)->templateContext());

        self::assertStringContainsString('L&#039;Atelier &amp; Co', $home);
        self::assertStringContainsString("{% extends 'base.html.twig' %}", $home);
    }
}
