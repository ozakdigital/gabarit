<?php

declare(strict_types=1);

namespace Gabarit\Core\Model;

use Gabarit\Core\Exception\InvalidSpecException;

/**
 * Identité de marque du projet généré (côté « marque blanche »).
 */
final readonly class Brand
{
    public const string DEFAULT_PRIMARY_COLOR = '#4f46e5';
    public const string DEFAULT_SECONDARY_COLOR = '#0f172a';

    /** Lettres, chiffres, espaces et ponctuation simple : rien qui puisse être interprété par Twig, YAML ou un shell. */
    private const string DISPLAY_NAME_PATTERN = "/^[\\p{L}\\p{N}][\\p{L}\\p{N} .,'&-]{0,59}$/u";
    private const string NAMESPACE_PATTERN = '/^[A-Z][A-Za-z0-9]{0,30}(\\\\[A-Z][A-Za-z0-9]{0,30}){0,3}$/';
    private const string COLOR_PATTERN = '/^#[0-9a-f]{6}$/';

    /** Mots réservés PHP interdits comme segment de namespace. */
    private const array RESERVED = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const',
        'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'final', 'finally',
        'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof',
        'insteadof', 'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print', 'private',
        'protected', 'public', 'readonly', 'require', 'return', 'static', 'switch', 'throw', 'trait', 'try',
        'unset', 'use', 'var', 'while', 'xor', 'yield', 'self', 'parent', 'true', 'false', 'null', 'int',
        'float', 'bool', 'string', 'void', 'iterable', 'object', 'mixed', 'never',
    ];

    public string $primaryColor;
    public string $secondaryColor;

    public function __construct(
        public string $displayName,
        public string $phpNamespace = 'App',
        string $primaryColor = self::DEFAULT_PRIMARY_COLOR,
        string $secondaryColor = self::DEFAULT_SECONDARY_COLOR,
        public ?string $logoPath = null,
    ) {
        $this->primaryColor = strtolower($primaryColor);
        $this->secondaryColor = strtolower($secondaryColor);

        $violations = [];

        if (1 !== preg_match(self::DISPLAY_NAME_PATTERN, $displayName)) {
            $violations['brand.displayName'] = "Le nom d'affichage doit faire 1 à 60 caractères (lettres, chiffres, espaces, . , ' & -).";
        }

        if (1 !== preg_match(self::NAMESPACE_PATTERN, $phpNamespace)) {
            $violations['brand.phpNamespace'] = 'Le namespace PHP doit ressembler à « Acme » ou « Acme\\Shop » (1 à 4 segments en PascalCase).';
        } else {
            foreach (explode('\\', $phpNamespace) as $segment) {
                if (\in_array(strtolower($segment), self::RESERVED, true)) {
                    $violations['brand.phpNamespace'] = \sprintf('« %s » est un mot réservé PHP et ne peut pas servir de namespace.', $segment);
                }
            }
        }

        foreach (['brand.primaryColor' => $this->primaryColor, 'brand.secondaryColor' => $this->secondaryColor] as $field => $color) {
            if (1 !== preg_match(self::COLOR_PATTERN, $color)) {
                $violations[$field] = 'Une couleur doit être au format hexadécimal #rrggbb.';
            }
        }

        if (null !== $logoPath && '' === trim($logoPath)) {
            $violations['brand.logoPath'] = 'Le chemin du logo ne peut pas être vide.';
        }

        if ([] !== $violations) {
            throw new InvalidSpecException($violations);
        }
    }

    /**
     * Marque par défaut déduite du nom de projet : « mon-app » → « Mon App », namespace « App ».
     */
    public static function fromProjectName(string $projectName): self
    {
        $displayName = ucwords(str_replace('-', ' ', $projectName));

        return new self('' !== trim($displayName) ? $displayName : 'App');
    }

    /**
     * Vendor Composer dérivé du premier segment du namespace : « AcmeCorp\Shop » → « acme-corp ».
     */
    public function composerVendor(): string
    {
        $first = explode('\\', $this->phpNamespace)[0];

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $first));
    }

    /**
     * @return array{displayName: string, phpNamespace: string, primaryColor: string, secondaryColor: string, logoPath: string|null}
     */
    public function toArray(): array
    {
        return [
            'displayName' => $this->displayName,
            'phpNamespace' => $this->phpNamespace,
            'primaryColor' => $this->primaryColor,
            'secondaryColor' => $this->secondaryColor,
            'logoPath' => $this->logoPath,
        ];
    }
}
