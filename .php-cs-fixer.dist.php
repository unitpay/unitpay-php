<?php

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/examples'])
    ->append([__DIR__ . '/UnitPay.php']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    // Dev machine runs PHP 8.5 while the project targets PHP >=7.4; allow the
    // newer runtime instead of exporting the deprecated PHP_CS_FIXER_IGNORE_ENV.
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRules([
        '@PSR12'            => true,
        'array_syntax'      => ['syntax' => 'short'],
        'no_unused_imports' => true,
    ])
    ->setFinder($finder);
