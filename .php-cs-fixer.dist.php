<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'node_modules', 'storage', 'uploads'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS2.0' => true,
        'no_break_comment' => false,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters', 'match']],
    ])
    ->setFinder($finder);
