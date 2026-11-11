<?php

declare(strict_types=1);

namespace Gabarit\Core\Model;

enum Database: string
{
    case PostgreSql = 'postgresql';
    case MySql = 'mysql';
    case MariaDb = 'mariadb';
    case Sqlite = 'sqlite';
}
