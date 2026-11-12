<?php

declare(strict_types=1);

namespace App\Controller;

use Gabarit\Core\Matrix\Matrix;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Matrice de compatibilité : l'UI construit ses menus à partir de cette réponse.
 */
final readonly class MatrixController
{
    public function __construct(private Matrix $matrix)
    {
    }

    #[Route('/api/matrix', name: 'api_matrix', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse($this->matrix->toArray());
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
