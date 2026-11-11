<?php

declare(strict_types=1);

namespace Gabarit\Core\Support;

/**
 * Édition de fichiers .env par blocs balisés « ###> id ### … ###< id ### » (format Symfony Flex).
 * Remplacer un bloc existant plutôt que l'ajouter rend les étapes idempotentes.
 */
final class EnvFile
{
    /**
     * @param array<string, string> $vars
     */
    public static function setBlock(string $content, string $id, array $vars, string $separator = '='): string
    {
        $lines = [];
        foreach ($vars as $key => $value) {
            $lines[] = \sprintf('%s%s%s', $key, $separator, self::quote($value));
        }
        $block = "###> $id ###\n".implode("\n", $lines)."\n###< $id ###";

        $pattern = '/^###> '.preg_quote($id, '/').' ###\R.*?^###< '.preg_quote($id, '/').' ###$/ms';
        if (1 === preg_match($pattern, $content)) {
            return (string) preg_replace_callback($pattern, static fn (): string => $block, $content, 1);
        }

        $content = rtrim($content, "\n");

        return ('' === $content ? '' : $content."\n\n").$block."\n";
    }

    /**
     * Fichier .env « à plat » (Laravel) : remplace la première ligne KEY=… (ou « # KEY=… » commentée),
     * sinon ajoute la variable à la fin. Idempotent.
     *
     * @param array<string, string> $vars
     */
    public static function set(string $content, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $line = $key.'='.self::quote($value);
            $pattern = '/^[ \t]*(#[ \t]*)?'.preg_quote($key, '/').'=.*$/m';
            if (1 === preg_match($pattern, $content)) {
                $content = (string) preg_replace_callback($pattern, static fn (): string => $line, $content, 1);
            } else {
                $content = rtrim($content, "\n")."\n".$line."\n";
            }
        }

        return $content;
    }

    /**
     * Lecture simple KEY=VALUE (suffisante pour les fichiers que Gabarit écrit lui-même).
     *
     * @return array<string, string>
     */
    public static function parse(string $content): array
    {
        $vars = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (1 !== preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=(.*)$/', $line, $m)) {
                continue;
            }
            $value = trim($m[2]);
            if (1 === preg_match('/^"(.*)"$/', $value, $q)) {
                $value = stripcslashes($q[1]);
            } elseif (1 === preg_match("/^'(.*)'$/", $value, $q)) {
                $value = $q[1];
            }
            $vars[$m[1]] = $value;
        }

        return $vars;
    }

    private static function quote(string $value): string
    {
        if (1 === preg_match('/^[A-Za-z0-9_.:\/@?=&%+-]*$/', $value) && !str_contains($value, '${')) {
            return '"'.$value.'"';
        }

        return '"'.addcslashes($value, '"\\$').'"';
    }
}
