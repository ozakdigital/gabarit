<?php

declare(strict_types=1);

namespace Gabarit\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le cœur ne dépend ni de la CLI ni du web (CLAUDE.md §2), et respecte les conventions §10.
 */
final class ArchitectureTest extends TestCase
{
    public function testCoreDoesNotDependOnApps(): void
    {
        foreach ($this->coreFiles() as $file) {
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/Gabarit\\\\(Cli|Web|Api)\\\\/', $source, $file);
            self::assertStringNotContainsString('Laravel\\Prompts', $source, $file);
        }
    }

    public function testStrictTypesAndFinalClasses(): void
    {
        foreach ($this->coreFiles() as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringContainsString('declare(strict_types=1);', $source, $file);
            if (1 === preg_match('/^(abstract |readonly |final )*class /m', $source, $m)) {
                self::assertStringContainsString('final', $m[0], $file.' : les classes sont final par défaut');
            }
        }
    }

    public function testNoShellStringCommands(): void
    {
        foreach ($this->coreFiles() as $file) {
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/\b(shell_exec|exec|system|passthru|popen|proc_open)\s*\(|Process::fromShellCommandline/', $source, $file);
        }
    }

    /**
     * @return list<string>
     */
    private function coreFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 2).'/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && 'php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }
        self::assertNotEmpty($files);

        return $files;
    }
}
