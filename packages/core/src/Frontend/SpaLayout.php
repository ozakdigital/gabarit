<?php

declare(strict_types=1);

namespace Gabarit\Core\Frontend;

/**
 * Organisation des fichiers d'une application SPA telle que la produit son installeur officiel,
 * et ce que Gabarit y remplace. Chemins relatifs au dossier frontend/.
 */
final readonly class SpaLayout
{
    /**
     * @param array<string, string> $renders     template Twig => fichier produit
     * @param list<string>          $remove      fichiers de démonstration devenus inutiles
     * @param string                $styleImport ligne qui importe brand.css dans $styleFile
     * @param string|null           $styleAfter  regex de la ligne après laquelle insérer l'import (null = en tête)
     * @param list<string>          $devCommand  commande du service Docker « node »
     */
    public function __construct(
        public array $renders,
        public array $remove,
        public string $styleFile,
        public string $styleImport,
        public ?string $styleAfter,
        public string $indexHtml,
        public array $devCommand,
    ) {
    }
}
