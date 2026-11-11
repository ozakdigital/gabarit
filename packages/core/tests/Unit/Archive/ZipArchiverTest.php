<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit\Archive;

use Gabarit\Core\Archive\ZipArchiver;
use Gabarit\Core\Tests\Support\TestCase;

final class ZipArchiverTest extends TestCase
{
    public function testArchiveIsDeterministicAndExcludesDependencies(): void
    {
        $dir = $this->tempDir();
        foreach (['b.txt' => 'b', 'a/c.txt' => 'c', '.env.local' => 'S=1', 'vendor/x.php' => 'x', 'frontend/node_modules/y.js' => 'y', '.git/HEAD' => 'h'] as $file => $content) {
            @mkdir(\dirname($dir.'/'.$file), 0o775, true);
            file_put_contents($dir.'/'.$file, $content);
        }

        $first = $this->tempDir().'/1.zip';
        $second = $this->tempDir().'/2.zip';
        (new ZipArchiver())->archive($dir, $first, 'mon-app');
        touch($dir.'/b.txt', time() + 100);
        (new ZipArchiver())->archive($dir, $second, 'mon-app');

        self::assertSame(hash_file('sha256', $first), hash_file('sha256', $second));

        $zip = new \ZipArchive();
        $zip->open($first);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        self::assertSame(['mon-app/.env.local', 'mon-app/a/c.txt', 'mon-app/b.txt'], $names);
    }
}
