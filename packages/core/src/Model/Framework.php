<?php

declare(strict_types=1);

namespace Gabarit\Core\Model;

enum Framework: string
{
    case Symfony = 'symfony';
    case Laravel = 'laravel';
    case CodeIgniter = 'codeigniter';
    case Slim = 'slim';
}
