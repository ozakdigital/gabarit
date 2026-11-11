<?php

declare(strict_types=1);

namespace Gabarit\Core\Framework;

use Gabarit\Core\Model\Framework;

final class FrameworkProfiles
{
    public static function for(Framework $framework): FrameworkProfile
    {
        return match ($framework) {
            Framework::Symfony => new SymfonyProfile(),
            Framework::Laravel => new LaravelProfile(),
            Framework::CodeIgniter => new CodeIgniterProfile(),
            Framework::Slim => new SlimProfile(),
        };
    }
}
