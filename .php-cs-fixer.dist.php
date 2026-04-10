<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/lib/MyImouto',
        __DIR__ . '/config',
        __DIR__ . '/script',
        __DIR__ . '/tests',
    ])
    ->exclude([
        'vendor',
        'node_modules',
        'public',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS2x0' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRiskyAllowed(false);
