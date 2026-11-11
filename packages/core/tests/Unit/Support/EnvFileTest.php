<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Support;

use Gabarit\Core\Support\EnvFile;
use PHPUnit\Framework\TestCase;

final class EnvFileTest extends TestCase
{
    public function testReplacesAnExistingBlock(): void
    {
        $env = "A=1\n\n###> symfony/mailer ###\nMAILER_DSN=null://null\n###< symfony/mailer ###\n\nB=2\n";

        $updated = EnvFile::setBlock($env, 'symfony/mailer', ['MAILER_DSN' => 'smtp://mailpit:1025']);

        self::assertSame("A=1\n\n###> symfony/mailer ###\nMAILER_DSN=\"smtp://mailpit:1025\"\n###< symfony/mailer ###\n\nB=2\n", $updated);
    }

    public function testAppendsAMissingBlockAndIsIdempotent(): void
    {
        $once = EnvFile::setBlock("A=1\n", 'gabarit/redis', ['REDIS_URL' => 'redis://redis:6379']);
        $twice = EnvFile::setBlock($once, 'gabarit/redis', ['REDIS_URL' => 'redis://redis:6379']);

        self::assertSame("A=1\n\n###> gabarit/redis ###\nREDIS_URL=\"redis://redis:6379\"\n###< gabarit/redis ###\n", $once);
        self::assertSame($once, $twice);
    }

    public function testParse(): void
    {
        $vars = EnvFile::parse("# commentaire\nA=1\nB=\"deux\"\nC='trois'\nlower=x\n");

        self::assertSame(['A' => '1', 'B' => 'deux', 'C' => 'trois'], $vars);
    }

    public function testRoundTripOfSpecialCharacters(): void
    {
        $env = EnvFile::setBlock('', 'x', ['V' => 'a"b$c\\d']);

        self::assertSame('a"b$c\\d', EnvFile::parse($env)['V']);
    }
}
