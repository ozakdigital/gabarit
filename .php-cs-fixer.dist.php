<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/packages/core/src', __DIR__.'/packages/core/tests', __DIR__.'/apps/cli/src', __DIR__.'/apps/cli/tests'])
    ->exclude('Fixtures')
    ->append([__DIR__.'/apps/cli/bin/gabarit', __FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache')
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'final_class' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'phpdoc_to_comment' => false,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
    ])
    ->setFinder($finder);
