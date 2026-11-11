<?php

declare(strict_types=1);

namespace Gabarit\Cli\Interview;

/**
 * Pose les questions du mode interactif. Deux implémentations : Laravel Prompts (menus riches)
 * et Symfony Console (repli pour Windows hors WSL et les terminaux non interactifs).
 */
interface Interviewer
{
    /**
     * @param array<string, string> $options valeur => libellé
     */
    public function select(string $label, array $options, ?string $default = null): string;

    /**
     * @param array<string, string> $options valeur => libellé
     *
     * @return list<string>
     */
    public function multiselect(string $label, array $options): array;

    /**
     * @param \Closure(string): ?string $validate renvoie un message d'erreur ou null
     */
    public function text(string $label, string $default, \Closure $validate): string;
}
