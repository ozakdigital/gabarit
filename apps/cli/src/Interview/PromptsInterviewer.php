<?php

declare(strict_types=1);

namespace Gabarit\Cli\Interview;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class PromptsInterviewer implements Interviewer
{
    public function select(string $label, array $options, ?string $default = null): string
    {
        return (string) select(label: $label, options: $options, default: $default, scroll: 10);
    }

    public function multiselect(string $label, array $options): array
    {
        $selected = multiselect(label: $label, options: $options, hint: 'Espace pour cocher, Entrée pour valider.');

        return array_values(array_map(static fn (int|string $v): string => (string) $v, $selected));
    }

    public function text(string $label, string $default, \Closure $validate): string
    {
        return text(label: $label, default: $default, required: true, validate: $validate);
    }
}
