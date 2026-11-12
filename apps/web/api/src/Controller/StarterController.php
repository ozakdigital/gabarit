<?php

declare(strict_types=1);

namespace App\Controller;

use Gabarit\Core\Exception\GenerationException;
use Gabarit\Core\Exception\InvalidSpecException;
use Gabarit\Core\Generator\Step\BrandStep;
use Gabarit\Core\Model\Brand;
use Gabarit\Core\Model\ProjectSpec;
use Gabarit\Core\Starter\StarterBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Construit le starter zip (modèle Spring Initializr) et l'envoie directement : rien n'est conservé
 * sur le serveur, aucune commande externe n'est lancée. L'installation réelle a lieu chez
 * l'utilisateur, via `bin/up`.
 */
final readonly class StarterController
{
    private const array LOGO_TYPES = ['image/png' => 'png', 'image/svg+xml' => 'svg'];

    public function __construct(
        private StarterBuilder $builder,
        #[Autowire(service: 'limiter.starter')]
        private RateLimiterFactory $limiter,
    ) {
    }

    #[Route('/api/starter', name: 'api_starter', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $limit = $this->limiter->create($request->getClientIp() ?? 'anonyme')->consume();
        if (!$limit->isAccepted()) {
            return new JsonResponse(['message' => 'Trop de demandes : réessayez dans quelques minutes.'], 429, [
                'Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()),
            ]);
        }

        $fs = new Filesystem();
        $workDir = $fs->tempnam(sys_get_temp_dir(), 'gabarit-starter-');
        $fs->remove($workDir);
        $fs->mkdir($workDir);

        try {
            $spec = $this->spec($request, $workDir);
            $zip = $workDir.'/'.$spec->name.'.zip';
            $this->builder->zip($spec, $zip);
        } catch (InvalidSpecException $e) {
            $fs->remove($workDir);

            return new JsonResponse(['message' => 'Choix invalides.', 'violations' => (object) $e->violations], 422);
        } catch (GenerationException $e) {
            $fs->remove($workDir);

            return new JsonResponse(['message' => $e->getMessage(), 'violations' => (object) []], 422);
        }

        // Le fichier est supprimé dès qu'il est envoyé ; le dossier de travail juste après.
        $response = new BinaryFileResponse($zip, 200, ['Content-Type' => 'application/zip'], false);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $spec->name.'.zip');
        $response->deleteFileAfterSend();
        register_shutdown_function(static fn () => $fs->remove($workDir));

        return $response;
    }

    /**
     * Choix envoyés en multipart : champ « spec » (JSON) et fichier « logo » facultatif.
     */
    private function spec(Request $request, string $workDir): ProjectSpec
    {
        $raw = $request->request->getString('spec');
        if ('' === $raw && str_contains((string) $request->headers->get('Content-Type'), 'json')) {
            $raw = $request->getContent();
        }

        try {
            $data = json_decode($raw, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidSpecException(['spec' => 'Choix illisibles (JSON attendu).']);
        }
        if (!\is_array($data)) {
            throw new InvalidSpecException(['spec' => 'Choix illisibles (objet JSON attendu).']);
        }
        // Le chemin du logo n'est jamais fourni par le client : seul le fichier envoyé compte.
        if (\is_array($data['brand'] ?? null)) {
            unset($data['brand']['logoPath']);
        }

        /** @var array<string, mixed> $data */
        $spec = ProjectSpec::fromArray($data);

        $logo = $request->files->get('logo');
        if (!$logo instanceof UploadedFile) {
            return $spec;
        }

        $extension = self::LOGO_TYPES[(string) $logo->getMimeType()] ?? null;
        if (!$logo->isValid() || null === $extension || $logo->getSize() > BrandStep::LOGO_MAX_BYTES) {
            throw new InvalidSpecException(['logo' => 'Logo refusé : PNG ou SVG de 1 Mo maximum.']);
        }
        $path = $logo->move($workDir, 'logo.'.$extension)->getPathname();
        try {
            BrandStep::assertSafeLogo($path);
        } catch (GenerationException $e) {
            throw new InvalidSpecException(['logo' => $e->getMessage()]);
        }

        return new ProjectSpec($spec->name, $spec->framework, $spec->frameworkVersion, $spec->phpVersion, $spec->database, $spec->frontend, $spec->services, new Brand(
            $spec->brand->displayName,
            $spec->brand->phpNamespace,
            $spec->brand->primaryColor,
            $spec->brand->secondaryColor,
            $path,
        ));
    }
}
