<?php

declare(strict_types=1);

namespace Gabarit\Core\Model;

enum Service: string
{
    case Redis = 'redis';
    case Minio = 'minio';
    case Mailpit = 'mailpit';
}
