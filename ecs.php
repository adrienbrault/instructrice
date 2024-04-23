<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\Import\GlobalNamespaceImportFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/examples',
    ])

    ->withRules([
        NoUnusedImportsFixer::class,
    ])

    ->withPreparedSets(
        psr12: true,
        common: true,
        strict: true,
        cleanCode: true,
    )

    ->withPhpCsFixerSets(
        symfony: true,
        symfonyRisky: true,
        php81Migration: true,
    )

    ->withSkip([
        ClassAttributesSeparationFixer::class => [
            __DIR__ . '/demo'
        ],
        GlobalNamespaceImportFixer::class,
    ])
;
