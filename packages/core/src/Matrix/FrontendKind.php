<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

/**
 * Famille d'installation d'un front.
 */
enum FrontendKind: string
{
    /** Rendu par le framework PHP (Twig, Blade). */
    case Server = 'server';
    /** Application créée par create-vite dans frontend/. */
    case Vite = 'vite';
    /** Application créée par Angular CLI dans frontend/. */
    case Angular = 'angular';

    public function isSpa(): bool
    {
        return self::Server !== $this;
    }
}
