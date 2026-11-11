<?php

declare(strict_types=1);

namespace Gabarit\Core\Matrix;

use Gabarit\Core\Model\Frontend;

final readonly class FrontendDefinition
{
    public function __construct(
        public Frontend $frontend,
        public string $label,
        public FrontendKind $kind = FrontendKind::Server,
        /** Paquet npm versionné de l'installeur officiel (create-vite@x, @angular/cli@x). */
        public ?string $installer = null,
        /** Modèle create-vite (vue-ts, react-ts…). */
        public ?string $template = null,
    ) {
    }
}
