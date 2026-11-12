<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testMatrixIsPublished(): void
    {
        $this->client->request('GET', '/api/matrix');

        self::assertResponseIsSuccessful();
        $matrix = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('laravel', $matrix['frameworks']);
        self::assertTrue($matrix['frontends']['react']['spa']);
    }

    public function testStarterZipIsDownloadedDirectly(): void
    {
        $this->client->request('POST', '/api/starter', ['spec' => json_encode(self::spec())]);

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertSame('application/zip', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename=boutique.zip', (string) $response->headers->get('Content-Disposition'));

        self::assertSame(['boutique/README.md', 'boutique/bin/up', 'boutique/bin/up.cmd', 'boutique/gabarit.json'], $this->sentZipEntries());
        // Aucun stockage : le fichier est supprimé une fois envoyé.
        self::assertFileDoesNotExist($response->getFile()->getPathname());
    }

    public function testInvalidChoicesAreExplained(): void
    {
        $this->client->request('POST', '/api/starter', ['spec' => json_encode(['phpVersion' => '7.4'] + self::spec())]);

        self::assertResponseStatusCodeSame(422);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('phpVersion', $data['violations']);
    }

    public function testUnreadableSpecIsRejected(): void
    {
        $this->client->request('POST', '/api/starter', ['spec' => '{oops']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLogoIsEmbedded(): void
    {
        $logo = tempnam(sys_get_temp_dir(), 'logo').'.svg';
        file_put_contents($logo, '<svg xmlns="http://www.w3.org/2000/svg"><circle r="4"/></svg>');

        $this->client->request('POST', '/api/starter', ['spec' => json_encode(self::spec())], [
            'logo' => new UploadedFile($logo, 'mon-logo.svg', 'image/svg+xml', null, true),
        ]);

        self::assertResponseIsSuccessful();
        self::assertContains('boutique/logo.svg', $this->sentZipEntries());
    }

    public function testScriptInSvgLogoIsRefused(): void
    {
        $logo = tempnam(sys_get_temp_dir(), 'logo').'.svg';
        file_put_contents($logo, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->client->request('POST', '/api/starter', ['spec' => json_encode(self::spec())], [
            'logo' => new UploadedFile($logo, 'logo.svg', 'image/svg+xml', null, true),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('logo', json_decode((string) $this->client->getResponse()->getContent(), true)['violations']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function spec(): array
    {
        return [
            'name' => 'boutique',
            'framework' => 'laravel',
            'frameworkVersion' => '13',
            'phpVersion' => '8.3',
            'database' => 'mariadb',
            'frontend' => 'blade',
            'services' => ['redis'],
            'brand' => ['displayName' => 'Boutique', 'phpNamespace' => 'Acme', 'primaryColor' => '#e11d48', 'secondaryColor' => '#0f172a', 'logoPath' => '/etc/passwd'],
        ];
    }

    /**
     * Entrées du zip réellement envoyé au navigateur.
     *
     * @return list<string>
     */
    private function sentZipEntries(): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sent');
        file_put_contents($path, $this->client->getInternalResponse()->getContent());
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();
        sort($names);

        return $names;
    }
}
